<?php

declare(strict_types=1);

namespace Shopper\Migrator\Support;

final class WriteResult
{
    public const int MAX_ERRORS = 100;

    /** @var array<int, array{handle: string, message: string}> */
    public array $errors = [];

    public int $migrated = 0;

    public int $failed = 0;

    public function success(): void
    {
        $this->migrated++;
    }

    public function failure(string $handle, string $message): void
    {
        $this->failed++;

        if (count($this->errors) < self::MAX_ERRORS) {
            $this->errors[] = ['handle' => $handle, 'message' => $message];
        }
    }

    public function merge(self $other): void
    {
        $this->migrated += $other->migrated;
        $this->failed += $other->failed;

        if (count($this->errors) < self::MAX_ERRORS) {
            $this->errors = array_slice([...$this->errors, ...$other->errors], 0, self::MAX_ERRORS);
        }
    }
}
