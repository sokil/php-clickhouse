<?php
declare(strict_types=1);

namespace Sokil\ClickHouse\Connection;

use Sokil\ClickHouse\Connection\Exception\ConnectError;
use Sokil\ClickHouse\Connection\Exception\RequestError;
use Sokil\ClickHouse\Connection\Exception\QueryError;

/**
 * HTTP transport
 */
class CurlConnection extends AbstractConnection
{
    /**
     * @var resource
     */
    private $curlSession;

    /**
     * @return resource
     */
    private function getCurlSession()
    {
        if ($this->curlSession === null) {
            $this->curlSession = curl_init();

            curl_setopt_array(
                $this->curlSession,
                [
                    CURLOPT_URL => sprintf('%s:%s', $this->getHost(), $this->getPort()),
                    CURLOPT_POST => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HEADER => true,
                    CURLOPT_CONNECTTIMEOUT_MS => $this->getConnectionTimeoutMs(),
                    CURLOPT_TIMEOUT_MS => $this->getRequestTimeoutMs(),
                    CURLOPT_HTTPHEADER => [
                        'Content-type' => 'text/plain; charset=UTF-8',
                    ],
                ]
            );
        }

        return $this->curlSession;
    }

    /**
     * Execution of native ClickHouse SQL query
     *
     * @param string $query
     *
     * @return string
     *
     * @throws ConnectError When response not fetched or non-HTTP response
     * @throws QueryError When response fetched and code ss not 200
     */
    public function execute(string $query): string
    {
        $curlSession = $this->getCurlSession();

        curl_setopt(
            $curlSession,
            CURLOPT_POSTFIELDS,
            $query
        );

        $response = curl_exec($curlSession);
        $responseCode = curl_getinfo($curlSession, CURLINFO_RESPONSE_CODE);

        if ($response === false) {
            throw new ConnectError(
                sprintf(
                    'Error connecting ClickHouse at %s:%s',
                    $this->getHost(),
                    $this->getPort()
                ),
                $responseCode
            );
        }

        if ($responseCode !== 200) {
            throw new QueryError($response, $responseCode);
        }

        return $response;
    }
}
