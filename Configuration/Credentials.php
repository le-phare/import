<?php

namespace LePhare\Import\Configuration;

use LePhare\Import\Exception\ImportException;

final class Credentials implements CredentialsInterface
{
    private string $host;

    private string $username;

    private string $password;

    private ?int $port;

    public function __construct(
        string $host,
        ?int $port,
        string $username,
        string $password,
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
    }

    public static function fromDatabaseUrl(string $url): self
    {
        $defaults = ['host' => null, 'user' => null, 'port' => null, 'pass' => null];

        // Use same regular expression as doctrine
        $normalizedUrl = preg_replace('#^((?:pdo_)?sqlite3?):///#', '$1://localhost/', $url);
        $params = parse_url($normalizedUrl);

        if (false === $params) {
            throw new ImportException('Malformed parameter "url".');
        }

        foreach ($params as $param => $value) {
            if (!is_string($value)) {
                continue;
            }

            $params[$param] = rawurldecode($value);
        }

        return new self($params['host'], $params['port'], $params['user'], $params['pass']);
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getPort(): ?int
    {
        return $this->port;
    }
}
