<?php

declare(strict_types=1);

namespace Shopper\Migrator\Contracts;

use Shopper\Migrator\Support\WriteResult;

interface EntityWriter
{
    /**
     * Persist a batch of source records into Shopper and report the outcome.
     *
     * @param  array<int, array<string, mixed>>  $records
     */
    public function write(array $records): WriteResult;
}
