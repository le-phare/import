<?php

namespace LePhare\Import\Strategy;

use LePhare\Import\ImportResource;

interface StrategyInterface
{
    public function getName(): string;

    public function copy(ImportResource $resource): int;
}
