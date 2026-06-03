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

    /**
     * Verify the store can actually reach and authenticate against the SureBright
     * webhook endpoint, using the same URL, credentials and headers a real event
     * dispatch uses. Sends a clearly marked, no-op connectivity probe and reports
     * the outcome in a human-readable form.
     *
     * Unlike {@see dispatch()} this never records a WebhookError row — it is a
     * deliberate, admin-triggered check, not a failed business event.
     *
     * The returned "detail" string always names the endpoint that was tried and,
     * when a request was made, the HTTP status and raw response body — so a
     * merchant can see exactly what error came back.
     *
     * @return array{success: bool, message: string, detail: string}
     */
    public function testConnectivity(): array
    {
        $eventDispatchUrl = self::SB_PARTNER_SERVICE_BASE_URL . "/partner/api/v1/webhook/magento/events";

        try {
            $activeIntegrationResponse = $this->sbOAuthClientRepository->getActiveClientIntegrationAuthDetails();
            if ($activeIntegrationResponse->isError || empty($activeIntegrationResponse->apiPayload)) {
                return $this->connectivityResult(
                    false,
                    'No active SureBright integration found ('
                        . ($activeIntegrationResponse->message ?? 'unknown')
                        . '). Connect the integration before testing webhooks.',
                    $eventDispatchUrl
                );
            }

            $apiPayload = is_object($activeIntegrationResponse->apiPayload)
                ? $activeIntegrationResponse->apiPayload
                : (object)$activeIntegrationResponse->apiPayload;

            $sbSvixAppId = $apiPayload->sb_svix_app_id ?? null;
            $sbSvixAccessToken = $apiPayload->sb_svix_access_token ?? null;
            $sbAccessToken = $apiPayload->sb_access_token ?? null;

            if (!$sbSvixAppId || !$sbSvixAccessToken || !$sbAccessToken) {
                return $this->connectivityResult(
                    false,
                    'Stored SureBright credentials are incomplete '
                        . '(missing sb_access_token / sb_svix_app_id / sb_svix_access_token).',
                    $eventDispatchUrl
                );
            }

            $this->curl->setHeaders([
                "Content-Type" => "application/json",
                "Accept" => "application/json",
                "sb-access-token" => $sbAccessToken,
            ]);

            $probePayload = json_encode([
                'eventType' => 'connectivity_test',
                'source' => 'magento_admin_panel',
                'message' => 'SureBright webhook connectivity check triggered from the Magento admin.',
            ]);

            $this->curl->post($eventDispatchUrl, $probePayload);
            $status = $this->curl->getStatus();
            $responseBody = (string)$this->curl->getBody();

            $this->logger->info(sprintf(
                'SureBright webhook connectivity test :: POST %s :: HTTP %d :: %s',
                $eventDispatchUrl,
                $status,
                $responseBody !== '' ? $responseBody : '(empty body)'
            ));

            if ($status === 0) {
                return $this->connectivityResult(
                    false,
                    'Could not reach SureBright — no HTTP response. '
                        . 'Outbound requests may be blocked by a firewall, or there is a DNS/network failure.',
                    $eventDispatchUrl,
                    $status,
                    $responseBody
                );
            }

            if ($status >= 200 && $status < 300) {
                return $this->connectivityResult(
                    true,
                    sprintf('SureBright webhook endpoint reachable and the test call was accepted (HTTP %d).', $status),
                    $eventDispatchUrl,
                    $status,
                    $responseBody
                );
            }

            if ($status === 401 || $status === 403) {
                return $this->connectivityResult(
                    false,
                    sprintf(
                        'Reached SureBright but authentication was rejected (HTTP %d). '
                        . 'The stored access token may be invalid — try reconnecting the integration.',
                        $status
                    ),
                    $eventDispatchUrl,
                    $status,
                    $responseBody
                );
            }

            return $this->connectivityResult(
                false,
                sprintf('SureBright webhook endpoint returned HTTP %d.', $status),
                $eventDispatchUrl,
                $status,
                $responseBody
            );
        } catch (\Throwable $exception) {
            $this->logger->info('SureBright webhook connectivity test failed :: ' . $exception->getMessage());
            return $this->connectivityResult(
                false,
                'Webhook connectivity test failed: ' . $exception->getMessage(),
                $eventDispatchUrl
            );
        }
    }

    /**
     * Build a connectivity-test result whose "detail" always records the URL that
     * was tried and, when available, the HTTP status and (truncated) response body.
     *
     * @param bool $success
     * @param string $message
     * @param string $url
     * @param int|null $status
     * @param string|null $responseBody
     * @return array{success: bool, message: string, detail: string}
     */
    private function connectivityResult(
        bool $success,
        string $message,
        string $url,
        ?int $status = null,
        ?string $responseBody = null
    ): array {
        $detail = 'Endpoint tried: ' . $url;

        if ($status !== null) {
            $detail .= "\nHTTP status: " . $status;
        }

        $body = $responseBody !== null ? trim($responseBody) : '';
        if ($body !== '') {
            if (strlen($body) > 1000) {
                $body = substr($body, 0, 1000) . '… (truncated)';
            }
            $detail .= "\nResponse: " . $body;
        }

        return ['success' => $success, 'message' => $message, 'detail' => $detail];
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
