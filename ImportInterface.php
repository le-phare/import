<?php

namespace LePhare\Import;

use Doctrine\Common\Collections\Collection;

interface ImportInterface
{
    /** @param array<string, mixed> $config */
    public function init(array $config): void;

    public function execute(bool $load = true): bool;

    public function load(): bool;

    public function copy(): void;

    public function getConfig(): Collection;
}
