<?php

namespace LePhare\Import\LoadStrategy;

class LoadNewestFirstStrategy implements LoadStrategyInterface
{
    public function getName(): string
    {
        return 'load_newest_first';
    }

    public function sort(array &$resources): void
    {
        usort($resources, fn (\SplFileInfo $a, \SplFileInfo $b) => $b->getCTime() <=> $a->getCTime());
    }
}
