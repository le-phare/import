<?php

namespace LePhare\Import\LoadStrategy;

class LoadOldestFirstStrategy implements LoadStrategyInterface
{
    public function getName(): string
    {
        return 'load_oldest_first';
    }

    public function sort(array &$resources): void
    {
        usort($resources, fn (\SplFileInfo $a, \SplFileInfo $b) => $a->getCTime() <=> $b->getCTime());
    }
}
