<?php

declare(strict_types=1);

namespace Shopper\Migrator\Sources\WooCommerce;

use Closure;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Shopper\Migrator\Exceptions\MigrationException;

final class WooCommerceClient
{
    public const int MAX_PER_PAGE = 100;

    private static ?Closure $hostResolver = null;

    private readonly string $host;

    private readonly int $port;

    private readonly ?string $address;

    public function __construct(
        private readonly string $storeUrl,
        private readonly string $consumerKey,
        private readonly string $consumerSecret,
    ) {
        $this->host = mb_strtolower((string) parse_url($storeUrl, PHP_URL_HOST));
        $this->port = (int) (parse_url($storeUrl, PHP_URL_PORT) ?: 443);
        $this->address = config('shopper.migrator.allow_local_hosts', false) ? null : $this->resolvePublicAddress();
    }

    public static function resolveHostsUsing(?Closure $resolver): void
    {
        self::$hostResolver = $resolver;
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

    private function resolvePublicAddress(): string
    {
        if (! str_starts_with(mb_strtolower($this->storeUrl), 'https://')) {
            throw new MigrationException(__('shopper-migrator::messages.errors.insecure_url'));
        }

        $addresses = filter_var($this->host, FILTER_VALIDATE_IP)
            ? [$this->host]
            : (self::$hostResolver ?? gethostbynamel(...))($this->host);

        if ($this->host === '' || ! $addresses) {
            throw new MigrationException(__('shopper-migrator::messages.errors.unknown_host', ['host' => $this->host ?: $this->storeUrl]));
        }

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) !== $address) {
                throw new MigrationException(__('shopper-migrator::messages.errors.forbidden_host', ['host' => $this->host]));
            }
        }

        return $addresses[0];
    }

    private function assertSameStore(RequestInterface $request, ResponseInterface $response, UriInterface $uri): void
    {
        if ($this->address !== null && ($uri->getHost() !== $this->host || ($uri->getPort() ?? 443) !== $this->port)) {
            throw new MigrationException(__('shopper-migrator::messages.errors.redirected', ['host' => $uri->getHost()]));
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
                'allow_redirects' => [
                    'max' => 5,
                    'strict' => true,
                    'referer' => false,
                    'protocols' => ['https'],
                    'on_redirect' => $this->assertSameStore(...),
                ],
            ])
            ->when($this->address !== null, fn (PendingRequest $request): PendingRequest => $request->withOptions([
                'curl' => [CURLOPT_RESOLVE => ["{$this->host}:{$this->port}:{$this->address}"]],
            ]))
            ->timeout(60)
            ->retry(3, 1000, throw: false)
            ->acceptJson();
    }
}
