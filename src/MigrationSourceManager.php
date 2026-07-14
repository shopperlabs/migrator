<?php

declare(strict_types=1);

namespace Shopper\Migrator;

use Closure;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Shopper\Migrator\Contracts\MigrationSource;

final class MigrationSourceManager
{
    /** @var array<string, MigrationSource> */
    private array $sources = [];

    /** @var array<string, Closure> */
    private array $customCreators = [];

    public function source(string $name): MigrationSource
    {
        return $this->sources[$name] ??= $this->resolve($name);
    }

    public function extend(string $name, Closure $callback): self
    {
        $this->customCreators[$name] = $callback;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function availableSources(): array
    {
        return array_keys($this->customCreators);
    }

    /**
     * @return Collection<string, MigrationSource>
     */
    public function sources(): Collection
    {
        return collect($this->availableSources())
            ->mapWithKeys(fn (string $name): array => [$name => $this->source($name)]);
    }

    private function resolve(string $name): MigrationSource
    {
        if (isset($this->customCreators[$name])) {
            return call_user_func($this->customCreators[$name], $name);
        }

        throw new InvalidArgumentException("Migration source [{$name}] is not supported.");
    }
}
