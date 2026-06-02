<?php

namespace Surebright\Integration\Service;

use Surebright\Integration\Model\SBOAuthClientRepository;
use Surebright\Integration\Model\WebhookError;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\SerializerInterface;
use Psr\Log\LoggerInterface;

class SBEventsDispatchService
{
    public const SB_SVIX_BASE_URL = "https://api.us.svix.com/api/v1/app/";
    public const SB_PARTNER_SERVICE_BASE_URL = "https://api2.surebright.com";
    private Curl $curl;
    private SerializerInterface $serializer;
    private LoggerInterface $logger;
    private SBOAuthClientRepository $sbOAuthClientRepository;
    private WebhookErrorRecorder $webhookErrorRecorder;

    public function __construct(
        Curl $curl,
        SerializerInterface $serializer,
        LoggerInterface $logger,
        SBOAuthClientRepository $sbOAuthClientRepository,
        WebhookErrorRecorder $webhookErrorRecorder
    ) {
        $this->curl = $curl;
        $this->serializer = $serializer;
        $this->logger = $logger;
        $this->sbOAuthClientRepository = $sbOAuthClientRepository;
        $this->webhookErrorRecorder = $webhookErrorRecorder;
    }


    public function dispatch(array $eventDetails)
    {
        $eventDispatchUrl = self::SB_PARTNER_SERVICE_BASE_URL . "/partner/api/v1/webhook/magento/events";

        try {
            $activeIntegrationResponse = $this->sbOAuthClientRepository->getActiveClientIntegrationAuthDetails();
            $this->logger->info(json_encode($activeIntegrationResponse));
            if ($activeIntegrationResponse->isError || empty($activeIntegrationResponse->apiPayload)) {
                $this->logger->info("Error in dispatch ::  err :: " . $activeIntegrationResponse->message);
                $this->safeRecord(
                    WebhookError::ERROR_TYPE_AUTH,
                    $eventDetails,
                    $eventDispatchUrl,
                    null,
                    null,
                    'Active integration not found: ' . ($activeIntegrationResponse->message ?? 'unknown')
                );
                return;
            }

            $apiPayload = is_object($activeIntegrationResponse->apiPayload) ? $activeIntegrationResponse->apiPayload : (object)$activeIntegrationResponse->apiPayload;

            $sbSvixAppId = $apiPayload->sb_svix_app_id ?? null;
            $sbSvixAccessToken = $apiPayload->sb_svix_access_token ?? null;
            $sbAccessToken = $apiPayload->sb_access_token ?? null;

            if (!$sbSvixAppId || !$sbSvixAccessToken || !$sbAccessToken) {
                $this->logger->info("Missing one or more required tokens");
                $this->safeRecord(
                    WebhookError::ERROR_TYPE_AUTH,
                    $eventDetails,
                    $eventDispatchUrl,
                    null,
                    null,
                    'Missing one or more required tokens (sb_svix_app_id / sb_svix_access_token / sb_access_token)'
                );
                return;
            }

            $headers = [
                "Content-Type" => "application/json",
                "Accept" => "application/json",
                "sb-access-token" => $sbAccessToken
            ];
            $this->curl->setHeaders($headers);

            $eventDetailsJson = json_encode($eventDetails);

            $this->curl->post($eventDispatchUrl, $eventDetailsJson);

            $status = $this->curl->getStatus();
            $responseBody = $this->curl->getBody();

            if ($status === 0 || $status >= 400) {
                $this->safeRecord(
                    WebhookError::ERROR_TYPE_HTTP,
                    $eventDetails,
                    $eventDispatchUrl,
                    $status,
                    $responseBody,
                    $status === 0
                        ? 'No HTTP response (network failure, blocked outbound, or DNS error)'
                        : 'Non-success HTTP status from Surebright webhook endpoint'
                );
            }
        } catch (\Throwable $exception) {
            $this->logger->info("Error in dispatch ::  err :: " . $exception->getMessage());
            $this->safeRecord(
                WebhookError::ERROR_TYPE_EXCEPTION,
                $eventDetails,
                $eventDispatchUrl,
                null,
                null,
                $exception->getMessage() . "\n" . $exception->getTraceAsString()
            );
            return;
        }
    }

    private function safeRecord(
        string $errorType,
        array $eventDetails,
        string $eventDispatchUrl,
        ?int $status,
        ?string $responseBody,
        string $errorMessage
    ): void {
        try {
            $this->webhookErrorRecorder->record(
                $errorType,
                $eventDetails,
                $eventDispatchUrl,
                $status,
                $responseBody,
                $errorMessage
            );
        } catch (\Throwable $t) {
            $this->logger->info('SBEventsDispatchService :: safeRecord swallowed error :: ' . $t->getMessage());
        }
    }
}
