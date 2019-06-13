<?php
declare(strict_types=1);

namespace Sokil\ClickHouse\Result;

class Result
{
    /**
     * @var string[]
     */
    private $headers;

    /**
     * @var string
     */
    private $body;

    /**
     * Result constructor.
     *
     * @param string[] $headers
     * @param string $body
     */
    public function __construct(array $headers, string $body)
    {
        $this->headers = $headers;
        $this->body = $body;
    }

    /**
     * @return string[]
     */
    public function getHeaders() : array
    {
        return $this->headers;
    }

    /**
     * @param string|null $name
     *
     * @return string
     */
    public function getHeader(string $name) : ?string
    {
        return $this->headers[$name] ?? null;
    }

    /**
     * @return string
     */
    public function getBody(): string
    {
        return $this->body;
    }
}