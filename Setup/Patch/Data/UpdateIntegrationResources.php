<?php
namespace Surebright\Integration\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Integration\Api\AuthorizationServiceInterface;
use Magento\Integration\Api\IntegrationServiceInterface;
use Magento\Integration\Model\ConfigBasedIntegrationManager;
use Magento\Integration\Model\IntegrationConfig;
use Surebright\Integration\Helper\SureBrightLogger;

/**
 * Re-grants the resource list from etc/integration/api.xml to the existing
 * "SureBright Product Protection" integration.
 *
 * NOTE: ConfigBasedIntegrationManager::processIntegrationConfig() only CREATES an
 * integration if it is missing; it does NOT update resources for an integration
 * that already exists. Stores that installed an earlier version therefore never
 * pick up newly added resources (e.g. Magento_InventoryApi::source) and the API
 * returns "The consumer isn't authorized to access %resources".
 *
 * This patch reads the resource list from the integration config and calls
 * AuthorizationServiceInterface::grantPermissions(), which rewrites the
 * integration's authorization_role / authorization_rule rows. The existing
 * access token keeps working immediately — no reauthorization required.
 */
class UpdateIntegrationResources implements DataPatchInterface
{
    const INTEGRATION_NAME = 'SureBright Product Protection';

    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @var IntegrationServiceInterface
     */
    private $integrationService;

    /**
     * @var AuthorizationServiceInterface
     */
    private $integrationAuthorizationService;

    /**
     * @var IntegrationConfig
     */
    private $integrationConfig;

    /**
     * @var ConfigBasedIntegrationManager
     */
    private $integrationManager;

    /**
     * @var SureBrightLogger
     */
    private $sbLogger;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param IntegrationServiceInterface $integrationService
     * @param AuthorizationServiceInterface $integrationAuthorizationService
     * @param IntegrationConfig $integrationConfig
     * @param ConfigBasedIntegrationManager $integrationManager
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        IntegrationServiceInterface $integrationService,
        AuthorizationServiceInterface $integrationAuthorizationService,
        IntegrationConfig $integrationConfig,
        ConfigBasedIntegrationManager $integrationManager
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->integrationService = $integrationService;
        $this->integrationAuthorizationService = $integrationAuthorizationService;
        $this->integrationConfig = $integrationConfig;
        $this->integrationManager = $integrationManager;
    }

    /**
     * {@inheritdoc}
     */
    public function apply()
    {
        $this->moduleDataSetup->getConnection()->startSetup();
        try {
            $this->sbLogger = \Magento\Framework\App\ObjectManager::getInstance()->create(SureBrightLogger::class);

            // Create the integration if it doesn't exist yet (fresh install safety net).
            $this->integrationManager->processIntegrationConfig([self::INTEGRATION_NAME]);

            $integration = $this->integrationService->findByName(self::INTEGRATION_NAME);
            if (!$integration || !$integration->getId()) {
                $this->sbLogger->logInstallationStep(
                    'UpdateIntegrationResources.php',
                    'Integration not found; skipping resource re-grant',
                    null,
                    'upgrade'
                );
                $this->moduleDataSetup->getConnection()->endSetup();
                return $this;
            }

            // Pull the resource list declared in etc/integration/api.xml.
            $integrations = $this->integrationConfig->getIntegrations();
            $resources = isset($integrations[self::INTEGRATION_NAME]['resource'])
                ? $integrations[self::INTEGRATION_NAME]['resource']
                : [];

            if (!empty($resources)) {
                // Rewrites authorization_role / authorization_rule for this integration.
                $this->integrationAuthorizationService->grantPermissions(
                    (int)$integration->getId(),
                    $resources
                );
                $this->sbLogger->logInstallationStep(
                    'UpdateIntegrationResources.php',
                    'Granted updated integration resources',
                    ['count' => count($resources)],
                    'upgrade'
                );
            }
        } catch (\Exception $e) {
            $this->sbLogger->logInstallationStep(
                'UpdateIntegrationResources.php',
                'Failed to re-grant integration resources',
                ['errorMessage' => $e->getMessage()],
                'upgrade'
            );
        }
        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies()
    {
        return [];
    }
}
