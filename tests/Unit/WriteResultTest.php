<?php

declare(strict_types=1);

use Shopper\Migrator\Support\WriteResult;

describe(WriteResult::class, function (): void {
    it('keeps the failed count exact while capping the stored error details', function (): void {
        $result = new WriteResult;

        foreach (range(1, WriteResult::MAX_ERRORS + 50) as $i) {
            $result->failure("handle-{$i}", 'boom');
        }

        expect($result->failed)->toBe(WriteResult::MAX_ERRORS + 50)
            ->and($result->errors)->toHaveCount(WriteResult::MAX_ERRORS);
    });

    it('caps the error details when merging two results', function (): void {
        $a = new WriteResult;
        $b = new WriteResult;

        foreach (range(1, WriteResult::MAX_ERRORS) as $i) {
            $a->failure("a-{$i}", 'boom');
            $b->failure("b-{$i}", 'boom');
        }

        $a->merge($b);

        expect($a->failed)->toBe(WriteResult::MAX_ERRORS * 2)
            ->and($a->errors)->toHaveCount(WriteResult::MAX_ERRORS);
    });
});
