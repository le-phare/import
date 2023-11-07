<?php

namespace LePhare\Import\LoadStrategy;

interface LoadStrategyInterface
{
    public function getName(): string;

    /**
     * @param \SplFileInfo[] $resources
     **/
    public function sort(array &$resources): void;
}
