<?php

declare(strict_types=1);

namespace Shopper\Migrator\Sources\WooCommerce\Writers;

use Illuminate\Database\Eloquent\Model;
use Shopper\Core\Enum\AddressType;
use Shopper\Core\Models\Contracts\Address as AddressContract;
use Shopper\Core\Models\Country;
use Shopper\Migrator\Contracts\EntityWriter;
use Shopper\Migrator\Support\WriteResult;
use Throwable;

final class CustomerWriter implements EntityWriter
{
    /**
     * @param  array<int, array<string, mixed>>  $records
     */
    public function write(array $records): WriteResult
    {
        $result = new WriteResult;

        foreach ($records as $customer) {
            try {
                $this->upsert($customer);
                $result->success();
            } catch (Throwable $e) {
                $result->failure((string) ($customer['email'] ?? $customer['id']), $e->getMessage());
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $customer
     */
    private function upsert(array $customer): void
    {
        $model = config('auth.providers.users.model');

        $user = $model::query()->firstOrCreate(
            ['email' => (string) $customer['email']],
            [
                'first_name' => $customer['first_name'] ?: ($customer['billing']['first_name'] ?? null),
                'last_name' => $customer['last_name'] ?: ($customer['billing']['last_name'] ?? ''),
                'opt_in' => false,
            ]
        );

        if (method_exists($user, 'assignRole')) {
            $user->assignRole(config('shopper.admin.roles.user', 'user'));
        }

        $this->writeAddress($user, (array) ($customer['billing'] ?? []), AddressType::Billing);
        $this->writeAddress($user, (array) ($customer['shipping'] ?? []), AddressType::Shipping);
    }

    /**
     * @param  array<string, mixed>  $address
     */
    private function writeAddress(Model $user, array $address, AddressType $type): void
    {
        if (($address['address_1'] ?? '') === '') {
            return;
        }

        $countryId = Country::query()
            ->where('cca2', mb_strtoupper((string) ($address['country'] ?? '')))
            ->value('id');

        if ($countryId === null) {
            return;
        }

        resolve(AddressContract::class)::query()->firstOrCreate(
            [
                'user_id' => $user->getKey(),
                'type' => $type->value,
                'street_address' => (string) $address['address_1'],
                'postal_code' => (string) ($address['postcode'] ?? ''),
            ],
            [
                'first_name' => $address['first_name'] ?: 'N/A',
                'last_name' => $address['last_name'] ?: 'N/A',
                'company_name' => $address['company'] ?: null,
                'street_address_plus' => $address['address_2'] ?: null,
                'city' => (string) ($address['city'] ?? ''),
                'state' => $address['state'] ?: null,
                'phone_number' => $address['phone'] ?? null,
                'country_id' => $countryId,
                'shipping_default' => $type === AddressType::Shipping,
                'billing_default' => $type === AddressType::Billing,
            ]
        );
    }
}
