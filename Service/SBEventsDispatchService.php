<?php

namespace Surebright\Integration\Service;

use Surebright\Integration\Model\SBOAuthClientRepository;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\SerializerInterface;
use Psr\Log\LoggerInterface;

class SBEventsDispatchService
{
    public const SB_SVIX_BASE_URL = "https://api.us.svix.com/api/v1/app/";
    private Curl $curl;
    private SerializerInterface $serializer;
    private LoggerInterface $logger;
    private SBOAuthClientRepository $sbOAuthClientRepository;

    public function __construct(
        Curl $curl,
        SerializerInterface $serializer,
        LoggerInterface $logger,
        SBOAuthClientRepository $sbOAuthClientRepository
    ) {
        $this->curl = $curl;
        $this->serializer = $serializer;
        $this->logger = $logger;
        $this->sbOAuthClientRepository = $sbOAuthClientRepository;
    }


    public function dispatch(array $eventDetails)
    {
        try {            
            $activeIntegrationResponse = $this->sbOAuthClientRepository->getActiveClientIntegrationAuthDetails();
            $this->logger->info(json_encode($activeIntegrationResponse));
            if ($activeIntegrationResponse->isError || empty($activeIntegrationResponse->apiPayload)) {
                // Fallback to handle error required
                $this->logger->info("Error in dispatch ::  err :: " . $activeIntegrationResponse->message);
                return;
            }

            $apiPayload = is_object($activeIntegrationResponse->apiPayload) ? $activeIntegrationResponse->apiPayload : (object)$activeIntegrationResponse->apiPayload;

            $sbSvixAppId = $apiPayload->sb_svix_app_id ?? null;
            $sbSvixAccessToken = $apiPayload->sb_svix_access_token ?? null;
            $sbAccessToken = $apiPayload->sb_access_token ?? null;

            if (!$sbSvixAppId || !$sbSvixAccessToken || !$sbAccessToken) {
                $this->logger->info("Missing one or more required tokens");
                return;
            }

            if (isset($eventDetails['payload']) && is_array($eventDetails['payload'])) {                
                $eventDetails['payload']['sbAccessToken'] = $sbAccessToken;
            }
            
            $headers = [
                "Content-Type: application/json",
                "Accept: application/json",
                "Authorization" => "Bearer $sbSvixAccessToken"
            ];
            $this->curl->setHeaders($headers);

            $eventDispatchUrl = self::SB_SVIX_BASE_URL . $sbSvixAppId . "/msg";
            $eventDetails = json_encode($eventDetails);

            $this->curl->post($eventDispatchUrl, $eventDetails);

            $status = $this->curl->getStatus();
            $responseBody = $this->curl->getBody();

            $response = $status . ' ' . $responseBody;
        } catch (\Exception $exception) {
            $this->logger->info("Error in dispatch ::  err :: " . $exception->getMessage());
            // Fallback to handle error required
            return;
        }
    }
}