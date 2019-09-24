<?php

declare(strict_types=1);

namespace Lamoda\OmsClient\V2;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\RequestOptions;
use Lamoda\OmsClient\Exception\OmsClientExceptionInterface;
use Lamoda\OmsClient\Exception\OmsGeneralErrorException;
use Lamoda\OmsClient\Exception\OmsRequestErrorException;
use Lamoda\OmsClient\Serializer\SerializerInterface;
use Lamoda\OmsClient\V2\Dto\CloseICArrayResponse;
use Lamoda\OmsClient\V2\Dto\GetICBufferStatusResponse;
use Lamoda\OmsClient\V2\Dto\GetICsFromOrderResponse;

final class OmsApi
{
    /**
     * @var ClientInterface
     */
    private $client;
    /**
     * @var SerializerInterface
     */
    private $serializer;

    public function __construct(ClientInterface $client, SerializerInterface $serializer)
    {
        $this->client = $client;
        $this->serializer = $serializer;
    }

    public function getICBufferStatus(
        Extension $extension,
        string $token,
        string $omsId,
        string $orderId,
        string $gtin
    ): GetICBufferStatusResponse {
        $url = sprintf('/api/v2/%s/buffer/status', (string)$extension);
        $result = $this->request($token, 'GET', $url, [
            'omsId' => $omsId,
            'orderId' => $orderId,
            'gtin' => $gtin,
        ]);

        /* @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->serializer->deserialize(GetICBufferStatusResponse::class, $result);
    }

    public function getICsFromOrder(
        Extension $extension,
        string $token,
        string $omsId,
        string $orderId,
        string $gtin,
        int $quantity,
        string $lastBlockId = '0'
    ): GetICsFromOrderResponse {
        $url = sprintf('/api/v2/%s/codes', (string)$extension);
        $result = $this->request($token, 'GET', $url, [
            'omsId' => $omsId,
            'orderId' => $orderId,
            'gtin' => $gtin,
            'quantity' => $quantity,
            'lastBlockId' => $lastBlockId,
        ]);

        /* @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->serializer->deserialize(GetICsFromOrderResponse::class, $result);
    }

    public function closeICArray(
        Extension $extension,
        string $token,
        string $omsId,
        string $orderId,
        string $gtin,
        string $lastBlockId
    ): CloseICArrayResponse {
        $url = sprintf('/api/v2/%s/buffer/close', (string)$extension);
        $result = $this->request($token, 'POST', $url, [
            'omsId' => $omsId,
            'orderId' => $orderId,
            'gtin' => $gtin,
            'lastBlockId' => $lastBlockId,
        ]);

        /* @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->serializer->deserialize(CloseICArrayResponse::class, $result);
    }

    /**
     * @throws OmsRequestErrorException
     */
    private function request(string $token, string $method, string $uri, array $query = [], $body = null): string
    {
        $options = [
            RequestOptions::BODY => $body,
            RequestOptions::HEADERS => [
                'Content-Type' => 'application/json',
                'clientToken' => $token,
            ],
            RequestOptions::QUERY => $query,
            RequestOptions::HTTP_ERRORS => true,
        ];

        $uri = ltrim($uri, '/');

        try {
            $result = $this->client->request($method, $uri, $options);
        } catch (\Throwable $exception) {
            /* @noinspection PhpUnhandledExceptionInspection */
            throw $this->handleRequestException($exception);
        }

        return (string)$result->getBody();
    }

    private function handleRequestException(\Throwable $exception): OmsClientExceptionInterface
    {
        if ($exception instanceof BadResponseException) {
            $response = $exception->getResponse();
            $responseBody = $response ? (string)$response->getBody() : '';
            $responseCode = $response ? $response->getStatusCode() : 0;

            return OmsRequestErrorException::becauseOfError($responseCode, $responseBody, $exception);
        }

        return OmsGeneralErrorException::becauseOfError($exception);
    }
}
