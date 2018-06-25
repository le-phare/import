<?php

namespace LePhare\Import\Strategy;

interface StrategyInterface
{
    public function getName();

    public function copy($resource);
}
