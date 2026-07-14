<?php

declare(strict_types=1);

namespace Shopper\Migrator\Sources\WooCommerce;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Shopper\Migrator\Exceptions\MigrationException;

final class WooCommerceClient
{
    public const int MAX_PER_PAGE = 100;

    public function __construct(
        private readonly string $storeUrl,
        private readonly string $consumerKey,
        private readonly string $consumerSecret,
    ) {
        $this->assertSafeStoreUrl($storeUrl);
    }

    public function perPage(): int
    {
        return min(self::MAX_PER_PAGE, (int) config('shopper.migrator.per_page', self::MAX_PER_PAGE));
    }

    public function verifySsl(): bool
    {
        if (app()->isProduction()) {
            return true;
        }

        return (bool) config('shopper.migrator.verify_ssl', true);
    }

    /**
     * Prevent the server from being aimed at internal services (SSRF): require HTTPS and
     * refuse literal private/reserved IPs and obvious internal hostnames. Reused on every
     * redirect hop (see request()) so a validated store cannot bounce the server onto an
     * internal target. Trusted local development opts out via the allow_local_hosts config.
     */
    private function assertSafeStoreUrl(string $url): void
    {
        if (config('shopper.migrator.allow_local_hosts', false)) {
            return;
        }

        if (! str_starts_with(mb_strtolower($url), 'https://')) {
            throw new MigrationException(__('shopper-migrator::messages.errors.insecure_url'));
        }

        $host = mb_strtolower((string) parse_url($url, PHP_URL_HOST));

        if ($host === '') {
            throw new MigrationException(__('shopper-migrator::messages.errors.forbidden_host', ['host' => $url]));
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new MigrationException(__('shopper-migrator::messages.errors.forbidden_host', ['host' => $host]));
            }

            return;
        }

        if ($host === 'localhost'
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.internal')) {
            throw new MigrationException(__('shopper-migrator::messages.errors.forbidden_host', ['host' => $host]));
        }
    }

    public function testConnection(): void
    {
        $response = $this->request()->get('products', ['per_page' => 1]);

        if ($response->unauthorized() || $response->forbidden()) {
            throw new MigrationException(__('shopper-migrator::messages.errors.unauthorized'));
        }

        if ($response->failed()) {
            throw new MigrationException(__('shopper-migrator::messages.errors.unreachable', ['status' => $response->status()]));
        }
    }

    public function total(string $endpoint): int
    {
        $response = $this->request()->get($endpoint, ['per_page' => 1]);

        if ($response->failed()) {
            return 0;
        }

        return (int) $response->header('X-WP-Total');
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<int, array<string, mixed>>
     */
    public function page(string $endpoint, int $page, int $perPage, array $query = []): array
    {
        return $this->get($endpoint, [
            ...$query,
            'page' => $page,
            'per_page' => $perPage,
            'orderby' => 'id',
            'order' => 'asc',
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function products(int $page, int $perPage): array
    {
        return $this->page('products', $page, $perPage, ['status' => 'any']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function variations(int $productId): array
    {
        return $this->all("products/{$productId}/variations");
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function categories(): array
    {
        return $this->all('products/categories');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function attributes(): array
    {
        return $this->all('products/attributes');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function attributeTerms(int $attributeId): array
    {
        return $this->all("products/attributes/{$attributeId}/terms");
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(string $endpoint): array
    {
        $records = [];
        $page = 1;

        do {
            $chunk = $this->page($endpoint, $page, 100);
            $records = [...$records, ...$chunk];
            $page++;
        } while (count($chunk) === 100);

        return $records;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<int, array<string, mixed>>
     */
    private function get(string $endpoint, array $query): array
    {
        $response = $this->request()->get($endpoint, $query);

        if ($response->failed()) {
            throw new MigrationException(__('shopper-migrator::messages.errors.request_failed', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
            ]));
        }

        return (array) $response->json();
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(mb_rtrim($this->storeUrl, '/').'/wp-json/wc/v3/')
            ->withBasicAuth($this->consumerKey, $this->consumerSecret)
            ->withOptions([
                'verify' => $this->verifySsl(),
                // A validated store URL can still 3xx the server onto an internal target, so
                // re-run the SSRF guard on every hop and refuse any downgrade to plain http.
                'allow_redirects' => [
                    'max' => 5,
                    'strict' => true,
                    'referer' => false,
                    'protocols' => ['https'],
                    'on_redirect' => function ($request, $response, $uri): void {
                        $this->assertSafeStoreUrl((string) $uri);
                    },
                ],
            ])
            ->timeout(60)
            ->retry(3, 1000, throw: false)
            ->acceptJson();
    }
}
