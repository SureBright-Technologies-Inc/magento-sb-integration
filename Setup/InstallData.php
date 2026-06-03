<?php
namespace Surebright\Integration\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Integration\Api\AuthorizationServiceInterface;
use Magento\Integration\Api\IntegrationServiceInterface;
use Magento\Integration\Model\ConfigBasedIntegrationManager;
use Magento\Integration\Model\IntegrationConfig;
use Magento\Framework\Setup\InstallDataInterface;
use Surebright\Integration\Helper\SureBrightLogger;

class InstallData implements InstallDataInterface{
    const INTEGRATION_NAME = 'SureBright Product Protection';

    /**
     * @var ConfigBasedIntegrationManager
     */

    private $integrationManager;
    private $integrationService;
    private $integrationAuthorizationService;
    private $integrationConfig;
    private $sbLogger;

    /**
     * @param ConfigBasedIntegrationManager $integrationManager
     * @param IntegrationServiceInterface $integrationService
     * @param AuthorizationServiceInterface $integrationAuthorizationService
     * @param IntegrationConfig $integrationConfig
     */

    public function __construct(
        ConfigBasedIntegrationManager $integrationManager,
        IntegrationServiceInterface $integrationService,
        AuthorizationServiceInterface $integrationAuthorizationService,
        IntegrationConfig $integrationConfig
    ) {
        $this->integrationManager = $integrationManager;
        $this->integrationService = $integrationService;
        $this->integrationAuthorizationService = $integrationAuthorizationService;
        $this->integrationConfig = $integrationConfig;
    }

    /**
     * {@inheritdoc}
     */

    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        try{
            $this->sbLogger = \Magento\Framework\App\ObjectManager::getInstance()->create(SureBrightLogger::class);
            $this->sbLogger->logInstallationStep('installData.php', 'Initiating SureBright Product Protection Integration',null,"install");

            // Creates the integration only if it does not already exist.
            $this->integrationManager->processIntegrationConfig([self::INTEGRATION_NAME]);

            // processIntegrationConfig does NOT update resources for an integration that
            // already exists (e.g. on a reinstall where the integration record survived the
            // uninstall). Re-grant the full resource list from etc/integration/api.xml so the
            // integration always reflects the current ACL set. The existing access token keeps
            // working — grantPermissions rewrites the role's rules, no reauthorization needed.
            $integration = $this->integrationService->findByName(self::INTEGRATION_NAME);
            if ($integration && $integration->getId()) {
                $integrations = $this->integrationConfig->getIntegrations();
                $resources = isset($integrations[self::INTEGRATION_NAME]['resource'])
                    ? $integrations[self::INTEGRATION_NAME]['resource']
                    : [];
                if (!empty($resources)) {
                    $this->integrationAuthorizationService->grantPermissions(
                        (int)$integration->getId(),
                        $resources
                    );
                    $this->sbLogger->logInstallationStep(
                        'installData.php',
                        'Granted SureBright integration resources',
                        ['count' => count($resources)],
                        "install"
                    );
                }
            }

            $this->sbLogger->logInstallationStep('installData.php', 'SureBright Product Protection Integration Completed',null,"install");
        }catch(\Exception $e){
            $this->sbLogger->logInstallationStep('installData.php', 'SureBright Product Protection Integration Failed', ["errorMessage" => $e->getMessage()], "install");
        }
    }
}
