<?php

namespace Surebright\Integration\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Integration\Api\IntegrationServiceInterface;
use Magento\Integration\Model\Integration;
use Magento\Authorization\Model\Acl\AclRetriever;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Module\PackageInfoFactory;
use Psr\Log\LoggerInterface;

/**
 * Backs the SureBright admin page (surebright/login/index).
 *
 * Exposes:
 *  - the connection status of the "SureBright Product Protection" integration,
 *  - the grouped resource (access) list declared in etc/integration/api.xml together
 *    with whether each resource is actually granted to the integration's ACL role,
 *  - the URLs/form key the page JS needs to run the per-group access tests and the
 *    disconnect/uninstall action.
 */
class Manage extends Template
{
    public const INTEGRATION_NAME = 'SureBright Product Protection';

    public const MODULE_NAME = 'Surebright_Integration';

    /**
     * Resource groups mirroring the <resources> declared in etc/integration/api.xml.
     * The "test" key maps to a case handled by \Surebright\Integration\Service\AccessTester.
     */
    private const RESOURCE_GROUPS = [
        [
            'label' => 'Backend Access',
            'test' => 'backend',
            'description' => 'Admin, system and store-view configuration scope.',
            'resources' => [
                'Magento_Backend::admin',
                'Magento_Backend::system',
                'Magento_Backend::stores',
                'Magento_Backend::store',
            ],
        ],
        [
            'label' => 'Sales Access',
            'test' => 'sales',
            'description' => 'Read orders, credit memos and shipments.',
            'resources' => [
                'Magento_Sales::sales',
                'Magento_Sales::sales_operation',
                'Magento_Sales::sales_creditmemo',
                'Magento_Sales::actions_view',
                'Magento_Sales::shipment',
            ],
        ],
        [
            'label' => 'Catalog Access',
            'test' => 'catalog',
            'description' => 'Read products, categories and attributes.',
            'resources' => [
                'Magento_Catalog::catalog',
                'Magento_Catalog::products',
                'Magento_Catalog::categories',
                'Magento_Catalog::attributes_attributes',
                'Magento_Catalog::update_attributes',
                'Magento_Catalog::sets',
            ],
        ],
        [
            'label' => 'Customer Information',
            'test' => 'customer',
            'description' => 'Read customer accounts and groups.',
            'resources' => [
                'Magento_Customer::customer',
                'Magento_Customer::manage',
                'Magento_Customer::online',
                'Magento_Customer::group',
            ],
        ],
        [
            'label' => 'Inventory Access',
            'test' => 'inventory',
            'description' => 'Read inventory sources and stock levels.',
            'resources' => [
                'Magento_CatalogInventory::cataloginventory',
                'Magento_InventoryApi::inventory',
                'Magento_InventoryApi::source',
                'Magento_InventoryApi::stock',
                'Magento_InventoryApi::stock_edit',
                'Magento_InventoryApi::stock_source_item_assign',
            ],
        ],
        [
            'label' => 'SureBright Custom API',
            'test' => 'custom',
            'description' => 'SureBright integration data and event APIs.',
            'resources' => [
                'Surebright_Integration::manage',
            ],
        ],
    ];

    /** @var IntegrationServiceInterface */
    private $integrationService;

    /** @var AclRetriever */
    private $aclRetriever;

    /** @var LoggerInterface */
    private $logger;

    /** @var PackageInfoFactory */
    private $packageInfoFactory;

    /** @var ModuleListInterface */
    private $moduleList;

    /** @var Integration|null */
    private $integration;

    /** @var bool */
    private $integrationLoaded = false;

    /** @var string[]|null */
    private $allowedResources;

    public function __construct(
        Context $context,
        IntegrationServiceInterface $integrationService,
        AclRetriever $aclRetriever,
        LoggerInterface $logger,
        PackageInfoFactory $packageInfoFactory,
        ModuleListInterface $moduleList,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->integrationService = $integrationService;
        $this->aclRetriever = $aclRetriever;
        $this->logger = $logger;
        $this->packageInfoFactory = $packageInfoFactory;
        $this->moduleList = $moduleList;
    }

    /**
     * @return string
     */
    public function getIntegrationName(): string
    {
        return self::INTEGRATION_NAME;
    }

    /**
     * Lazily load the integration record by name.
     *
     * @return Integration|null
     */
    private function getIntegration()
    {
        if (!$this->integrationLoaded) {
            try {
                $integration = $this->integrationService->findByName(self::INTEGRATION_NAME);
                $this->integration = ($integration && $integration->getId()) ? $integration : null;
            } catch (\Exception $e) {
                $this->logger->error('SureBright Manage block: ' . $e->getMessage());
                $this->integration = null;
            }
            $this->integrationLoaded = true;
        }
        return $this->integration;
    }

    /**
     * Whether the SureBright integration exists and is registered in Magento.
     *
     * @return bool
     */
    public function isConnected(): bool
    {
        return $this->getIntegration() !== null;
    }

    /**
     * Whether the SureBright integration has been activated by an admin
     * (i.e. exists and its status is active). A pre-configured integration is
     * created at setup time but stays inactive until activated from
     * System > Extensions > Integrations.
     *
     * @return bool
     */
    public function isActivated(): bool
    {
        $integration = $this->getIntegration();

        return $integration !== null
            && (int)$integration->getStatus() === Integration::STATUS_ACTIVE;
    }

    /**
     * Whether an already-activated integration is missing any resource declared
     * in etc/integration/api.xml.
     *
     * Config-based integrations are created once at setup:upgrade. When new
     * resources are later added to api.xml and setup:upgrade is re-run, Magento
     * will NOT silently re-grant the new scope to an integration that is already
     * activated — the live OAuth token keeps the scope it was issued with until
     * the merchant reauthorizes. Those new resources therefore show up here as
     * "not granted", which is the signal that a reauthorization is needed.
     *
     * @return bool
     */
    public function needsReauthorization(): bool
    {
        if (!$this->isActivated()) {
            return false;
        }

        foreach ($this->getResourceGroups() as $group) {
            if ($group['granted_count'] < $group['total_count']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resources actually granted to the integration's authorization role.
     *
     * @return string[]
     */
    private function getAllowedResources(): array
    {
        if ($this->allowedResources === null) {
            $this->allowedResources = [];
            $integration = $this->getIntegration();
            if ($integration) {
                try {
                    $this->allowedResources = $this->aclRetriever->getAllowedResourcesByUser(
                        UserContextInterface::USER_TYPE_INTEGRATION,
                        (int)$integration->getId()
                    );
                } catch (\Exception $e) {
                    $this->logger->error('SureBright Manage block (acl): ' . $e->getMessage());
                }
            }
        }
        return $this->allowedResources;
    }

    /**
     * Whether a single resource id is granted to the integration role.
     *
     * @param string $resource
     * @return bool
     */
    private function isResourceGranted(string $resource): bool
    {
        $allowed = $this->getAllowedResources();
        return in_array('Magento_Backend::all', $allowed, true) || in_array($resource, $allowed, true);
    }

    /**
     * Grouped resource list enriched with per-resource granted status.
     *
     * @return array
     */
    public function getResourceGroups(): array
    {
        $groups = [];
        foreach (self::RESOURCE_GROUPS as $group) {
            $resources = [];
            $grantedCount = 0;
            foreach ($group['resources'] as $resource) {
                $granted = $this->isResourceGranted($resource);
                if ($granted) {
                    $grantedCount++;
                }
                $resources[] = ['name' => $resource, 'granted' => $granted];
            }
            $groups[] = [
                'label' => $group['label'],
                'test' => $group['test'],
                'description' => $group['description'],
                'resources' => $resources,
                'granted_count' => $grantedCount,
                'total_count' => count($resources),
            ];
        }
        return $groups;
    }

    /**
     * @return string
     */
    public function getTestUrl(): string
    {
        return $this->getUrl('surebright/access/test');
    }

    /**
     * @return string
     */
    public function getWebhookTestUrl(): string
    {
        return $this->getUrl('surebright/webhook/test');
    }

    /**
     * @return string
     */
    public function getReindexUrl(): string
    {
        return $this->getUrl('surebright/indexer/reindex');
    }

    /**
     * @return string
     */
    public function getCachePurgeUrl(): string
    {
        return $this->getUrl('surebright/cache/purge');
    }

    /**
     * URL of Magento's Integrations grid, where the merchant activates the
     * SureBright integration.
     *
     * @return string
     */
    public function getIntegrationsGridUrl(): string
    {
        return $this->getUrl('adminhtml/integration/');
    }

    /**
     * Installed version of the SureBright module, read from its composer.json
     * (falling back to the declared module setup version).
     *
     * @return string
     */
    public function getModuleVersion(): string
    {
        try {
            $version = $this->packageInfoFactory->create()->getVersion(self::MODULE_NAME);
            if ($version !== '') {
                return $version;
            }
        } catch (\Exception $e) {
            $this->logger->error('SureBright Manage block (version): ' . $e->getMessage());
        }

        $module = $this->moduleList->getOne(self::MODULE_NAME);

        return isset($module['setup_version']) ? (string)$module['setup_version'] : 'unknown';
    }

    /**
     * @return string
     */
    public function getUninstallUrl(): string
    {
        return $this->getUrl('surebright/integration/uninstall');
    }

    /**
     * @return string
     */
    public function getPageFormKey(): string
    {
        return $this->formKey->getFormKey();
    }
}
