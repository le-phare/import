<?php

namespace LePhare\Import\LoadStrategy;

class LoadReverseAlphabeticallyStrategy implements LoadStrategyInterface
{
    public function getName(): string
    {
        return 'load_reverse_alphabetically';
    }

    public function sort(array &$resources): void
    {
        krsort($resources);
    }
}
