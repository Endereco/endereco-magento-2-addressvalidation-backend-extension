<?php
declare(strict_types=1);

namespace Parc\AddressValidation\Service;

use GuzzleHttp\ClientFactory;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ResponseFactory;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Framework\App\Config\ScopeConfigInterface;
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
     * @var ResponseFactory
     */
    private ResponseFactory $responseFactory;

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
     * @param ResponseFactory      $responseFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param LoggerInterface      $logger
     */
    public function __construct(
        ClientFactory        $clientFactory,
        ResponseFactory      $responseFactory,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface      $logger
    ) {
        $this->clientFactory   = $clientFactory;
        $this->responseFactory = $responseFactory;
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
     * @return Response
     */
    private function doRequest($body): Response
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
            $response = $this->responseFactory->create([
                'status' => $exception->getCode(),
                'reason' => $exception->getMessage()
            ]);
        }

        return $response;
    }
}
