<?php

namespace LePhare\Import\Strategy;

interface StrategyRepositoryInterface
{
    public function addStrategy(StrategyInterface $strategy);

    public function getStrategy(string $name): ?StrategyInterface;
}
