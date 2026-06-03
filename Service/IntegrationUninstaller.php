<?php

namespace Surebright\Integration\Service;

use Magento\Integration\Api\IntegrationServiceInterface;
use Magento\Integration\Api\OauthServiceInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\HTTP\Client\Curl;
use Surebright\Integration\Model\SBOAuthClientRepository;
use Surebright\Integration\Helper\SureBrightLogger;
use Psr\Log\LoggerInterface;

/**
 * Disconnects / revokes the SureBright integration without removing the module
 * files from disk (which requires the bin/magento module:uninstall CLI command).
 *
 * Steps, each best-effort and independently logged:
 *   1. Notify the SureBright backend (derived from the integration endpoint).
 *   2. Revoke & delete the OAuth integration token.
 *   3. Delete the OAuth consumer.
 *   4. Delete the Magento integration record.
 *   5. Clear the local surebrightIntegration auth table.
 */
class IntegrationUninstaller
{
    public const INTEGRATION_NAME = 'SureBright Product Protection';

    /** @var IntegrationServiceInterface */
    private $integrationService;

    /** @var OauthServiceInterface */
    private $oauthService;

    /** @var ResourceConnection */
    private $resourceConnection;

    /** @var SBOAuthClientRepository */
    private $sbOAuthClient;

    /** @var Curl */
    private $curl;

    /** @var LoggerInterface */
    private $logger;

    /** @var SureBrightLogger */
    private $sbLogger;

    public function __construct(
        IntegrationServiceInterface $integrationService,
        OauthServiceInterface $oauthService,
        ResourceConnection $resourceConnection,
        SBOAuthClientRepository $sbOAuthClient,
        Curl $curl,
        LoggerInterface $logger,
        SureBrightLogger $sbLogger
    ) {
        $this->integrationService = $integrationService;
        $this->oauthService = $oauthService;
        $this->resourceConnection = $resourceConnection;
        $this->sbOAuthClient = $sbOAuthClient;
        $this->curl = $curl;
        $this->logger = $logger;
        $this->sbLogger = $sbLogger;
    }

    /**
     * Run the disconnect flow.
     *
     * @return array{success: bool, message: string, steps: array<int, array{step: string, ok: bool, detail: string}>}
     */
    public function uninstall(): array
    {
        $steps = [];

        // Snapshot the active client details before anything is removed, so we can
        // notify the SureBright backend while the token is still valid.
        $activeClient = $this->getActiveClientData();

        $integration = null;
        try {
            $integration = $this->integrationService->findByName(self::INTEGRATION_NAME);
            if (!$integration || !$integration->getId()) {
                $integration = null;
            }
        } catch (\Exception $e) {
            $this->logger->error('SureBright uninstall (findByName): ' . $e->getMessage());
        }

        // 1. Notify SureBright backend (best-effort, non-fatal).
        $steps[] = $this->notifySurebright($integration, $activeClient);

        $consumerId = $integration ? (int)$integration->getConsumerId() : 0;

        // 2. Revoke & delete the integration access token.
        $steps[] = $this->runStep('Revoke OAuth token', function () use ($consumerId) {
            if (!$consumerId) {
                return 'No consumer id; nothing to revoke.';
            }
            $this->oauthService->deleteIntegrationToken($consumerId);
            return 'Integration token revoked.';
        });

        // 3. Delete the OAuth consumer.
        $steps[] = $this->runStep('Delete OAuth consumer', function () use ($consumerId) {
            if (!$consumerId) {
                return 'No consumer id; nothing to delete.';
            }
            $this->oauthService->deleteConsumer($consumerId);
            return 'OAuth consumer deleted.';
        });

        // 4. Delete the Magento integration record.
        $steps[] = $this->runStep('Delete integration', function () use ($integration) {
            if (!$integration) {
                return 'Integration record not found; skipped.';
            }
            $this->integrationService->delete((int)$integration->getId());
            return 'Integration record deleted.';
        });

        // 5. Clear the local SureBright auth table.
        $steps[] = $this->runStep('Clear local auth data', function () {
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName(
                SBOAuthClientRepository::SB_INTEGRATION_AUTH_CLIENT_TABLE
            );
            if ($connection->isTableExists($table)) {
                $deleted = $connection->delete($table);
                return sprintf('Removed %d stored credential row(s).', (int)$deleted);
            }
            return 'Auth table not present; nothing to clear.';
        });

        $success = true;
        foreach ($steps as $step) {
            if (!$step['ok']) {
                $success = false;
            }
        }

        $message = $success
            ? 'SureBright was disconnected. The integration, OAuth token and stored credentials have been removed.'
            : 'SureBright was disconnected with some warnings — see the step details below.';

        try {
            $this->sbLogger->logInstallationStep(
                'IntegrationUninstaller.php',
                'SureBright disconnect executed',
                ['success' => $success, 'steps' => $steps],
                'uninstall'
            );
        } catch (\Exception $e) {
            $this->logger->error('SureBright uninstall (log): ' . $e->getMessage());
        }

        return ['success' => $success, 'message' => $message, 'steps' => $steps];
    }

    /**
     * Best-effort POST to the SureBright backend so it can mark the store disconnected.
     *
     * @param \Magento\Integration\Model\Integration|null $integration
     * @param array $activeClient
     * @return array{step: string, ok: bool, detail: string}
     */
    private function notifySurebright($integration, array $activeClient): array
    {
        return $this->runStep('Notify SureBright', function () use ($integration, $activeClient) {
            $endpoint = $integration ? (string)$integration->getEndpoint() : '';
            if ($endpoint === '') {
                return 'No endpoint configured; skipped remote notice.';
            }
            // Derive the uninstall URL from the configured OAuth initialize endpoint.
            $url = str_replace('/oauth/initialize', '/oauth/uninstall', $endpoint);

            $payload = [
                'sbIntegrationClientUUID' => $activeClient['sb_integration_client_uuid'] ?? null,
                'consumerKey' => $activeClient['consumer_key'] ?? null,
                'event' => 'magento.integration.uninstalled',
            ];

            $this->curl->addHeader('Content-Type', 'application/json');
            if (!empty($activeClient['sb_access_token'])) {
                $this->curl->addHeader('Authorization', 'Bearer ' . $activeClient['sb_access_token']);
            }
            $this->curl->setTimeout(10);
            $this->curl->post($url, json_encode($payload));

            $status = $this->curl->getStatus();
            if ($status >= 200 && $status < 300) {
                return 'SureBright backend notified (HTTP ' . $status . ').';
            }
            // Non-2xx is non-fatal; the local disconnect still proceeds.
            return 'SureBright backend returned HTTP ' . $status . '; local disconnect continued.';
        });
    }

    /**
     * Pull the most recent active stored credential row.
     *
     * @return array
     */
    private function getActiveClientData(): array
    {
        try {
            $response = $this->sbOAuthClient->getActiveClientIntegrationAuthDetails();
            $data = $response->apiPayload;
            return is_array($data) ? $data : [];
        } catch (\Exception $e) {
            $this->logger->error('SureBright uninstall (active client): ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Execute a single step, capturing success/failure without aborting the flow.
     *
     * @param string $label
     * @param callable $callback returns a detail string on success
     * @return array{step: string, ok: bool, detail: string}
     */
    private function runStep(string $label, callable $callback): array
    {
        try {
            $detail = (string)$callback();
            return ['step' => $label, 'ok' => true, 'detail' => $detail];
        } catch (\Exception $e) {
            $this->logger->error('SureBright uninstall [' . $label . ']: ' . $e->getMessage());
            return ['step' => $label, 'ok' => false, 'detail' => $e->getMessage()];
        }
    }
}
