<?php

declare(strict_types=1);

namespace Parc\AddressValidation\Service;

use GuzzleHttp\ClientFactory;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class EnderecoApi
{
    protected $apiKey;

    /**
     * API request URL
     */
    private const API_REQUEST_URI = 'https://endereco-service.de/';
    /**
     * API request endpoint
     *
     * @var string
     */
    private const API_REQUEST_ENDPOINT = 'rpc/v1';

    /**
     * HTTP statuses observed for a rejected/invalid API key. Treated the same as a
     * connection failure (see doRequest()): this is a problem with the API access
     * itself, not with any particular order's address data, so it won't resolve
     * itself for the next order either.
     */
    private const AUTH_FAILURE_STATUSES = [401, 403, 421];

    /**
     * @var ClientFactory
     */
    private ClientFactory $clientFactory;

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var bool
     */
    private bool $logApiTraffic;

    /**
     * GitApiService constructor
     *
     * @param ClientFactory        $clientFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param LoggerInterface      $logger
     */
    public function __construct(
        ClientFactory $clientFactory,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger
    ) {
        $this->clientFactory   = $clientFactory;
        $this->scopeConfig     = $scopeConfig;
        $this->logger          = $logger;

        $this->apiKey        = (string)$this->scopeConfig->getValue('parc_addressvalidation/general/api_key');
        $this->logApiTraffic = (bool)$this->scopeConfig->getValue('parc_addressvalidation/general/log_api_traffic');
    }

    /**
     * Fetch some data from API
     */
    public function execute($body): string
    {
        $response     = $this->doRequest($body);
        $responseBody = $response->getBody();
        $content      = $responseBody->getContents();

        // Written to its own var/log/endereco_api.log (see di.xml), not the shared
        // debug.log — that handler has no admin on/off switch of its own and would
        // write every debug() call unconditionally. Since this payload is customer
        // address data, gate it behind our own "Log API Traffic" config too.
        if ($this->logApiTraffic) {
            $this->logger->debug('Endereco API response', [
                'status' => $response->getStatusCode(),
                'body'   => $content,
            ]);
        }

        return $content;
    }

    /**
     * @param $body
     *
     * @return ResponseInterface
     */
    private function doRequest($body): ResponseInterface
    {
        $client = $this->clientFactory->create([
            'config' => [
                'base_uri'        => self::API_REQUEST_URI,
                'connect_timeout' => 5,
                'timeout'         => 10,
            ]
        ]);

        if ($this->logApiTraffic) {
            $this->logger->debug('Endereco API request', ['body' => $body]);
        }

        try {
            $response = $client->request(Request::HTTP_METHOD_POST, static::API_REQUEST_ENDPOINT, [
                'headers' => [
                    'X-Auth-Key' => $this->apiKey
                ],
                'body'    => $body,
            ]);
        } catch (GuzzleException $exception) {
            $this->logger->warning('Endereco API request failed', [
                'message' => $exception->getMessage(),
            ]);

            $upstreamResponse = $exception instanceof RequestException ? $exception->getResponse() : null;

            if ($upstreamResponse === null || in_array($upstreamResponse->getStatusCode(), self::AUTH_FAILURE_STATUSES, true)) {
                // A rejected key or a connection failure isn't specific to this order's
                // address data - every other order in the same run would fail the exact
                // same way, and the underlying cause won't resolve itself mid-run. Let the
                // caller decide to stop the whole batch instead of retrying (and failing)
                // once per remaining order.
                throw new EnderecoApiUnavailableException(
                    $upstreamResponse !== null
                        ? sprintf(
                            'Endereco API rejected the request (HTTP %d %s)',
                            $upstreamResponse->getStatusCode(),
                            $upstreamResponse->getReasonPhrase()
                        )
                        : sprintf('Endereco API connection failed: %s', $exception->getMessage()),
                    0,
                    $exception
                );
            }

            // Some other upstream error (e.g. a genuinely malformed request) - not treated
            // as systemic, so let the caller's normal per-order handling deal with it.
            $response = $upstreamResponse;
        }

        return $response;
    }
}
