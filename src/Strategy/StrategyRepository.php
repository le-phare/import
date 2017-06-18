<?php

namespace LePhare\Import\Strategy;

class StrategyRepository implements StrategyRepositoryInterface
{
    private $strategies = array();

    public function addStrategy(StrategyInterface $strategy)
    {
        $this->strategies[$strategy->getName()] = $strategy;

        return $this;
    }

    public function getStrategy($name)
    {
        return isset($this->strategies[$name]) ?
            $this->strategies[$name] :
            null
        ;
    }
}
