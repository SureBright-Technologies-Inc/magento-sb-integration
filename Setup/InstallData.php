<?php
namespace Surebright\Integration\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Integration\Model\ConfigBasedIntegrationManager;
use Magento\Framework\Setup\InstallDataInterface;
use Surebright\Integration\Helper\SureBrightLogger;

class InstallData implements InstallDataInterface{
    /**
     * @var ConfigBasedIntegrationManager
     */

    private $integrationManager;
    private $sbLogger;

    /**
     * @param ConfigBasedIntegrationManager $integrationManager
     */

    public function __construct(ConfigBasedIntegrationManager $integrationManager)
    {
        $this->integrationManager = $integrationManager;
    }

    /**
     * {@inheritdoc}
     */

    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        try{
            $this->sbLogger = \Magento\Framework\App\ObjectManager::getInstance()->create(SureBrightLogger::class);
            $this->sbLogger->logInstallationStep('installData.php', 'Initiating SureBright Product Protection Integration',null,"install");
            $this->integrationManager->processIntegrationConfig(['SureBright Product Protection']);
            $this->sbLogger->logInstallationStep('installData.php', 'SureBright Product Protection Integration Completed',null,"install");
        }catch(\Exception $e){
            $this->sbLogger->logInstallationStep('installData.php', 'SureBright Product Protection Integration Failed', ["errorMessage" => $e->getMessage()], "install");
        }
    }
}