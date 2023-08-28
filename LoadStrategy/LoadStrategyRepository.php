<?php

namespace LePhare\Import\LoadStrategy;

class LoadStrategyRepository implements LoadStrategyRepositoryInterface
{
    private array $strategies = [];

    public function addLoadStrategy(LoadStrategyInterface $strategy)
    {
        $this->strategies[$strategy->getName()] = $strategy;

        return $this;
    }

    public function getLoadStrategy(string $name): ?LoadStrategyInterface
    {
        return $this->strategies[$name] ?? null
        ;
    }

    public function getLoadStrategies(): array
    {
        return $this->strategies;
    }
}
