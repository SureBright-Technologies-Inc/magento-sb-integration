<?php

namespace Surebright\Integration\Service;

use Psr\Log\LoggerInterface;
use Surebright\Integration\Model\WebhookError;
use Surebright\Integration\Model\WebhookErrorFactory;
use Surebright\Integration\Model\ResourceModel\WebhookError as WebhookErrorResource;

class WebhookErrorRecorder
{
    private WebhookErrorFactory $webhookErrorFactory;
    private WebhookErrorResource $webhookErrorResource;
    private LoggerInterface $logger;

    public function __construct(
        WebhookErrorFactory $webhookErrorFactory,
        WebhookErrorResource $webhookErrorResource,
        LoggerInterface $logger
    ) {
        $this->webhookErrorFactory = $webhookErrorFactory;
        $this->webhookErrorResource = $webhookErrorResource;
        $this->logger = $logger;
    }

    public function record(
        string $errorType,
        ?array $payload,
        ?string $requestUrl,
        ?int $httpStatus,
        ?string $responseBody,
        ?string $errorMessage
    ): void {
        try {
            $eventData = $this->getEventData($payload);

            /** @var WebhookError $model */
            $model = $this->webhookErrorFactory->create();
            $model->setData([
                'event_type'      => $eventData['event_type'],
                'error_type'      => $errorType,
                // Preserve 0 (network failure) distinctly from null (no HTTP attempt).
                'http_status'     => $httpStatus,
                'entity_id'       => $eventData['entity_id'],
                'entity_sku'      => $eventData['entity_sku'],
                'store_id'        => $eventData['store_id'],
                'request_url'     => $requestUrl,
                'request_payload' => $this->encodePayload($payload),
                'response_body'   => $this->truncate($responseBody, 65000),
                'error_message'   => $this->truncate($errorMessage, 65000),
            ]);

            $this->webhookErrorResource->save($model);
        } catch (\Throwable $t) {
            $this->logger->info('WebhookErrorRecorder :: failed to persist webhook error :: ' . $t->getMessage());
        }
    }


    private function getEventData(?array $payload): array
    {
        $data = $payload['data'] ?? [];

        $eventType = $data['sbEventType'] ?? 'unknown';
        $entityId = $data['productId'] ?? $data['orderId'] ?? null;
        $entitySku = $data['productSku'] ?? null;

        // Fallback chain for store_id: explicit (orders) → first website (products) → null.
        $storeId = $data['storeId'] ?? null;
        if ($storeId === null
            && isset($data['websites'][0])
            && is_array($data['websites'][0])
            && isset($data['websites'][0]['websiteId'])
        ) {
            $storeId = $data['websites'][0]['websiteId'];
        }

        return [
            'event_type' => is_string($eventType) ? $eventType : 'unknown',
            'entity_id'  => $entityId !== null ? (string) $entityId : null,
            'entity_sku' => $entitySku !== null ? (string) $entitySku : null,
            'store_id'   => is_numeric($storeId) ? (int) $storeId : null,
        ];
    }

    /**
     * json_encode with safe flags. Without these, invalid UTF-8 in a payload
     * (rare, but happens with certain CSV imports) returns false → the
     * payload column would be lost exactly when we need it most for debugging.
     */
    private function encodePayload(?array $payload): ?string
    {
        if ($payload === null) {
            return null;
        }
        $json = json_encode(
            $payload,
            JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES
        );
        return $json !== false ? $json : '<json_encode failed: ' . json_last_error_msg() . '>';
    }

    private function truncate(?string $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }
        return strlen($value) > $max ? substr($value, 0, $max) : $value;
    }
}
