<?php
declare(strict_types=1);

namespace Sokil\ClickHouse\Connection\Struct;

class Timeval
{
    /**
     * @var int
     */
    private $seconds;

    /**
     * @var int
     */
    private $microSeconds;

    /**
     * Timeval constructor.
     *
     * @param int $seconds
     * @param int $microSeconds
     */
    public function __construct(int $seconds, int $microSeconds)
    {
        $this->seconds = $seconds;
        $this->microSeconds = $microSeconds;
    }

    /**
     * @return int
     */
    public function getSeconds(): int
    {
        return $this->seconds;
    }

    /**
     * @return int
     */
    public function getMicroSeconds(): int
    {
        return $this->microSeconds;
    }
}