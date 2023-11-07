<?php

namespace LePhare\Import\Configuration;

interface CredentialsInterface
{
    public function getHost(): string;

    public function getUsername(): string;

    public function getPassword(): string;

    public function getPort(): ?int;
}
