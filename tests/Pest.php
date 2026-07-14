<?php

declare(strict_types=1);

use Shopper\Migrator\Tests\DatabaseTestCase;
use Shopper\Migrator\Tests\TestCase;

pest()->extend(TestCase::class)->in('Unit');
pest()->extend(DatabaseTestCase::class)->in('Feature');

function wooFixture(string $name): array
{
    return (array) json_decode((string) file_get_contents(__DIR__."/fixtures/{$name}.json"), true);
}
