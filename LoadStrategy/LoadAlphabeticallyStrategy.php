<?php

namespace LePhare\Import\LoadStrategy;

class LoadAlphabeticallyStrategy implements LoadStrategyInterface
{
    public function getName(): string
    {
        return 'load_alphabetically';
    }

    public function sort(array &$resources): void
    {
        ksort($resources);
    }
}
