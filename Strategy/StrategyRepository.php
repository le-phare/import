<?php

namespace LePhare\Import\Strategy;

class StrategyRepository implements StrategyRepositoryInterface
{
    private array $strategies = [];

    public function addStrategy(StrategyInterface $strategy): self
    {
        $this->strategies[$strategy->getName()] = $strategy;

        return $this;
    }

    public function getStrategy(string $name): ?StrategyInterface
    {
        return $this->strategies[$name] ?? null;
    }
}
