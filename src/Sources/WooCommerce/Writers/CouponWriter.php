<?php

declare(strict_types=1);

namespace Shopper\Migrator\Sources\WooCommerce\Writers;

use Illuminate\Support\Carbon;
use Shopper\Core\Enum\DiscountApplyTo;
use Shopper\Core\Enum\DiscountEligibility;
use Shopper\Core\Enum\DiscountRequirement;
use Shopper\Core\Enum\DiscountType;
use Shopper\Core\Models\Discount;
use Shopper\Migrator\Contracts\EntityWriter;
use Shopper\Migrator\Support\WriteResult;
use Throwable;

final class CouponWriter implements EntityWriter
{
    /**
     * @param  array<int, array<string, mixed>>  $records
     */
    public function write(array $records): WriteResult
    {
        $result = new WriteResult;

        foreach ($records as $coupon) {
            try {
                $this->upsert($coupon);
                $result->success();
            } catch (Throwable $e) {
                $result->failure((string) ($coupon['code'] ?? $coupon['id']), $e->getMessage());
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $coupon
     */
    private function upsert(array $coupon): void
    {
        $code = mb_strtoupper((string) $coupon['code']);

        if (Discount::query()->where('code', $code)->exists()) {
            return;
        }

        $wcType = (string) ($coupon['discount_type'] ?? 'fixed_cart');
        $isPercentage = $wcType === 'percent';

        $dropped = $this->droppedRestrictions($coupon, $wcType);
        $isRestricted = $dropped !== [];

        $expiresAt = $this->date($coupon['date_expires_gmt'] ?? $coupon['date_expires'] ?? null);
        $startAt = $this->date($coupon['date_created_gmt'] ?? $coupon['date_created'] ?? null) ?? now();

        $minimumAmount = (float) ($coupon['minimum_amount'] ?? 0);

        Discount::query()->create([
            'code' => $code,
            'type' => $isPercentage ? DiscountType::Percentage : DiscountType::FixedAmount,
            'value' => $isPercentage
                ? max(0, min(100, (int) round((float) $coupon['amount'])))
                : max(0, (int) round(((float) $coupon['amount']) * 100)),
            'apply_to' => DiscountApplyTo::Order->value,
            'eligibility' => DiscountEligibility::Everyone->value,
            'min_required' => $minimumAmount > 0
                ? DiscountRequirement::Price->value
                : DiscountRequirement::None->value,
            'min_required_value' => $minimumAmount > 0 ? (string) (int) round($minimumAmount * 100) : null,
            'usage_limit' => $coupon['usage_limit'] ?? null,
            'usage_limit_per_user' => (int) ($coupon['usage_limit_per_user'] ?? 0) > 0,
            'total_use' => max(0, (int) ($coupon['usage_count'] ?? 0)),
            'combinable' => ! (bool) ($coupon['individual_use'] ?? false),
            'is_active' => ! $isRestricted && ($expiresAt === null || $expiresAt->isFuture()),
            'start_at' => $startAt,
            'end_at' => $expiresAt,
            'metadata' => $isRestricted ? [
                'migration' => [
                    'source' => 'woocommerce',
                    'reason' => 'restrictions_not_migrated',
                    'wc_discount_type' => $wcType,
                    'dropped' => $dropped,
                ],
            ] : null,
        ]);
    }

    /**
     * WooCommerce restrictions Shopper's order-level discount cannot represent. When any is
     * present the coupon is imported disabled rather than silently widened to the whole store.
     *
     * @param  array<string, mixed>  $coupon
     * @return array<int, string>
     */
    private function droppedRestrictions(array $coupon, string $wcType): array
    {
        $dropped = [];

        foreach (['product_ids', 'excluded_product_ids', 'product_categories', 'excluded_product_categories', 'email_restrictions'] as $key) {
            if (! empty($coupon[$key])) {
                $dropped[] = $key;
            }
        }

        if ((bool) ($coupon['exclude_sale_items'] ?? false)) {
            $dropped[] = 'exclude_sale_items';
        }

        if (($coupon['limit_usage_to_x_items'] ?? null) !== null) {
            $dropped[] = 'limit_usage_to_x_items';
        }

        if ((float) ($coupon['maximum_amount'] ?? 0) > 0) {
            $dropped[] = 'maximum_amount';
        }

        // fixed_product applies the amount to each matching item, which an order-level
        // discount would misprice — treat it as a scope we must not flatten.
        if ($wcType === 'fixed_product') {
            $dropped[] = 'fixed_product';
        }

        return $dropped;
    }

    private function date(mixed $value): ?Carbon
    {
        return $value !== null && $value !== ''
            ? Carbon::parse((string) $value, 'UTC')
            : null;
    }
}
