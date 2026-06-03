<?php
declare(strict_types=1);

namespace Surebright\Integration\Setup;

use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Integration\Api\AuthorizationServiceInterface;
use Magento\Integration\Api\IntegrationServiceInterface;
use Magento\Integration\Model\Config\Consolidated\Reader as IntegrationConfigReader;
use Psr\Log\LoggerInterface;

/**
 * Recurring data setup — runs on EVERY `bin/magento setup:upgrade`.
 *
 * WHY THIS EXISTS
 * ----------------
 * Our integration is a Config-type integration declared in
 * etc/integration/api.xml. Magento's stock mechanism for propagating that
 * manifest to the authorization_rule table is broken for *existing*
 * integrations in two ways (verified against Magento 2.4 source; see Magento
 * issues #28356 and #35365):
 *
 *   1. Setup/InstallData.php (InstallDataInterface, NON-recurring) calls
 *      processIntegrationConfig() — but that runs only ONCE on first install
 *      and is version-gated, so any resource we ADD to api.xml in a later
 *      module version never reaches an already-installed store.
 *
 *   2. Magento\Integration\Setup\Recurring -> ConfigBasedIntegrationManager
 *      ::processConfigBasedIntegrations reads the manifest from the
 *      `config_integration_api` cache (cache id `integration-consolidated`),
 *      which is routinely STALE during setup:upgrade. So newly added
 *      resources stay `deny` in authorization_rule while older ones stay
 *      `allow`. And when it DOES detect a diff, it deletes + recreates the
 *      integration (Magento\Integration\Model\IntegrationService::delete then
 *      a fresh _oauthService->createConsumer) which ROTATES the OAuth
 *      consumer + access token — unacceptable for a live store whose
 *      credentials we depend on.
 *
 * WHAT THIS DOES INSTEAD
 * ----------------------
 *   - Reads the resource list straight from the on-disk XML via the
 *     Consolidated Reader (NO cache), so it always reflects the deployed
 *     api.xml regardless of cache state or cache:clean ordering. The Reader's
 *     converter injects parent resources automatically.
 *   - Calls AuthorizationServiceInterface::grantPermissions(), which ONLY
 *     rewrites the integration role's authorization_rule rows. It does NOT
 *     create/replace the OAuth consumer or token — existing live credentials
 *     keep working.
 *   - Runs on every setup:upgrade, so all future manifest changes propagate
 *     automatically with no new patch/class required.
 *
 * Net effect: deploying a new module version with changed api.xml +
 * `bin/magento setup:upgrade` reliably grants the declared resources to the
 * existing integration, with zero token rotation and zero dependence on
 * cache-clean ordering.
 */
class RecurringData implements InstallDataInterface
{
    private const INTEGRATION_NAME = 'SureBright Product Protection';

    /** @var IntegrationServiceInterface */
    private $integrationService;

    /** @var AuthorizationServiceInterface */
    private $authorizationService;

    /** @var IntegrationConfigReader */
    private $integrationConfigReader;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        IntegrationServiceInterface $integrationService,
        AuthorizationServiceInterface $authorizationService,
        IntegrationConfigReader $integrationConfigReader,
        LoggerInterface $logger
    ) {
        $this->integrationService = $integrationService;
        $this->authorizationService = $authorizationService;
        $this->integrationConfigReader = $integrationConfigReader;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        try {
            $integration = $this->integrationService->findByName(self::INTEGRATION_NAME);
            if (!$integration || !$integration->getId()) {
                // Integration not created yet (not installed/activated). Magento's
                // own first-install create path grants resources correctly because
                // the consolidated cache is fresh on first install. Nothing to do.
                return;
            }

            // Read resources fresh from disk. Structure: [name => ['resource' => [...]]]
            // with parent resources already injected by the Consolidated Converter.
            // Bypasses the integration-consolidated cache entirely.
            $integrations = $this->integrationConfigReader->read();
            $resources = $integrations[self::INTEGRATION_NAME]['resource'] ?? [];

            if (empty($resources)) {
                $this->logger->warning(
                    'Surebright_Integration RecurringData: no resources resolved from api.xml; skipping grant'
                );
                return;
            }

            // Token-safe: rewrites authorization_rule for the integration role only.
            // Does NOT rotate the OAuth consumer/access token.
            $this->authorizationService->grantPermissions(
                (int) $integration->getId(),
                $resources
            );

            $this->logger->info(sprintf(
                'Surebright_Integration RecurringData: granted %d resources to integration %d',
                count($resources),
                (int) $integration->getId()
            ));
        } catch (\Throwable $e) {
            // Never break setup:upgrade because of permission sync; log for diagnosis.
            $this->logger->error(
                'Surebright_Integration RecurringData error: ' . $e->getMessage()
            );
        }
    }
}
