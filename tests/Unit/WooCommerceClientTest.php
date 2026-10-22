<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response as PsrResponse;
use GuzzleHttp\Psr7\Uri;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Shopper\Migrator\Exceptions\MigrationException;
use Shopper\Migrator\Sources\WooCommerce\WooCommerceClient;

function wooClient(): WooCommerceClient
{
    return new WooCommerceClient(
        storeUrl: 'https://store.test',
        consumerKey: 'ck_test',
        consumerSecret: 'cs_test',
    );
}

describe(WooCommerceClient::class, function (): void {
    it('counts records from the total header', function (): void {
        Http::fake([
            'store.test/wp-json/wc/v3/products*' => Http::response([], 200, ['X-WP-Total' => '412']),
        ]);

        expect(wooClient()->total('products'))->toBe(412);
    });

    it('throws a readable error when the credentials are rejected', function (): void {
        Http::fake([
            'store.test/*' => Http::response(['code' => 'woocommerce_rest_cannot_view'], 401),
        ]);

        wooClient()->testConnection();
    })->throws(MigrationException::class);

    it('fetches products with pagination parameters', function (): void {
        Http::fake([
            'store.test/wp-json/wc/v3/products*' => Http::response([['id' => 1]]),
        ]);

        $products = wooClient()->products(page: 2, perPage: 100);

        expect($products)->toHaveCount(1);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'page=2')
            && str_contains($request->url(), 'per_page=100')
            && $request->hasHeader('Authorization'));
    });

    it('fetches every product status so drafts and private products are not dropped', function (): void {
        Http::fake([
            'store.test/wp-json/wc/v3/products*' => Http::response([['id' => 1]]),
        ]);

        wooClient()->products(page: 1, perPage: 100);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'status=any'));
    });

    it('paginates variations beyond the first hundred', function (): void {
        Http::fake([
            'store.test/wp-json/wc/v3/products/799/variations*' => Http::sequence()
                ->push(array_map(fn (int $i): array => ['id' => $i], range(1, 100)))
                ->push([['id' => 101]]),
        ]);

        expect(wooClient()->variations(799))->toHaveCount(101);
    });

    it('paginates attributes instead of stopping at the default page size', function (): void {
        Http::fake([
            'store.test/wp-json/wc/v3/products/attributes*' => Http::sequence()
                ->push(array_map(fn (int $i): array => ['id' => $i, 'name' => "A{$i}", 'slug' => "a{$i}"], range(1, 100)))
                ->push([['id' => 101, 'name' => 'A101', 'slug' => 'a101']]),
        ]);

        expect(wooClient()->attributes())->toHaveCount(101);
    });

    it('clamps the configured page size to the WooCommerce hard cap', function (): void {
        config(['shopper.migrator.per_page' => 250]);

        expect(wooClient()->perPage())->toBe(100);
    });

    it('rejects a non-https store url', function (): void {
        new WooCommerceClient('http://store.test', 'ck', 'cs');
    })->throws(MigrationException::class);

    it('rejects a store url pointing at a link-local metadata address', function (): void {
        new WooCommerceClient('https://169.254.169.254', 'ck', 'cs');
    })->throws(MigrationException::class);

    it('rejects a hostname that resolves to a private address', function (string $address): void {
        WooCommerceClient::resolveHostsUsing(fn (): array => ['8.8.8.8', $address]);

        new WooCommerceClient('https://store.example.com', 'ck', 'cs');
    })->with(['127.0.0.1', '10.0.0.5', '169.254.169.254', '100.100.100.200'])->throws(MigrationException::class);

    it('rejects a hostname that does not resolve', function (): void {
        WooCommerceClient::resolveHostsUsing(fn (): bool => false);

        new WooCommerceClient('https://missing.example.com', 'ck', 'cs');
    })->throws(MigrationException::class);

    it('pins requests to the resolved address and refuses redirects to another host', function (): void {
        $options = [];

        Http::fake(function (Request $request, array $sent) use (&$options) {
            $options = $sent;

            return Http::response();
        });

        wooClient()->testConnection();

        expect($options['curl'][CURLOPT_RESOLVE])->toBe(['store.test:443:8.8.8.8'])
            ->and(fn () => $options['allow_redirects']['on_redirect'](
                new PsrRequest('GET', 'https://store.test/wp-json/wc/v3/products'),
                new PsrResponse(302),
                new Uri('https://169.254.169.254/latest/meta-data'),
            ))->toThrow(MigrationException::class);
    });

    it('allows local and plain-http hosts when explicitly enabled', function (): void {
        config(['shopper.migrator.allow_local_hosts' => true]);

        expect(new WooCommerceClient('http://localhost:8080', 'ck', 'cs'))
            ->toBeInstanceOf(WooCommerceClient::class);
    });

    it('honours the verify_ssl config outside production', function (): void {
        config(['shopper.migrator.verify_ssl' => false]);

        expect(wooClient()->verifySsl())->toBeFalse();
    });

    it('never disables ssl verification in production even when configured off', function (): void {
        app()->detectEnvironment(fn (): string => 'production');
        config(['shopper.migrator.verify_ssl' => false]);

        expect(wooClient()->verifySsl())->toBeTrue();
    });
});
