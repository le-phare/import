<?php

namespace LePhare\Import\Load;

use LePhare\Import\ImportResource;

interface LoaderInterface
{
    public const FILE = 'file';

    public function supports(ImportResource $resource, array $context): bool;

    /**
     * @return int the number of resource loaded
     */
    public function load(ImportResource $resource, array $context): int;
}
