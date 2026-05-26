<?php
declare(strict_types=1);

namespace Parc\AddressValidation\Service;

use GuzzleHttp\ClientFactory;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ResponseFactory;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Framework\App\Config\ScopeConfigInterface;

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
     * GitApiService constructor
     *
     * @param ClientFactory        $clientFactory
     * @param ResponseFactory      $responseFactory
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ClientFactory        $clientFactory,
        ResponseFactory      $responseFactory,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->clientFactory   = $clientFactory;
        $this->responseFactory = $responseFactory;
        $this->scopeConfig     = $scopeConfig;

        $this->apiKey = $this->scopeConfig->getValue('parc_addressvalidation/general/api_key');
    }

    /**
     * Fetch some data from API
     */
    public function execute($body): string
    {
        $response     = $this->doRequest($body);
        $responseBody = $response->getBody();

        return $responseBody->getContents();
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

        try {
            $response = $client->request(Request::HTTP_METHOD_POST, static::API_REQUEST_ENDPOINT, [
                'headers' => [
                    'X-Auth-Key' => $this->apiKey
                ],
                'body'    => $body,
            ]);
        } catch (GuzzleException $exception) {
            $response = $this->responseFactory->create([
                'status' => $exception->getCode(),
                'reason' => $exception->getMessage()
            ]);
        }

        return $response;
    }
}
