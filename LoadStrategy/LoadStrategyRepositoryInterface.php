<?php

namespace LePhare\Import\LoadStrategy;

interface LoadStrategyRepositoryInterface
{
    public function addLoadStrategy(LoadStrategyInterface $strategy);

    public function getLoadStrategy(string $name): ?LoadStrategyInterface;

    /** @return array<string, LoadStrategyInterface> */
    public function getLoadStrategies(): array;
}
