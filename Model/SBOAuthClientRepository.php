<?php

namespace Surebright\Integration\Model;

use Surebright\Integration\Api\SBOAuthClientInterface;
use Surebright\Integration\Api\ApiResponse;
use Magento\Integration\Api\IntegrationServiceInterface;
use Magento\Integration\Api\OauthServiceInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class SBOAuthClientRepository implements SBOAuthClientInterface
{

    
    public const SB_INTEGRATION_AUTH_CLIENT_TABLE = 'surebrightIntegration';
    private $integrationService;
    private $oauthService;
    private $encryptor;
    private $logger;
    private SchemaSetupInterface $schemaSetup;

    /**
     * SBOAuthClientRepository constructor
     *
     * @param IntegrationServiceInterface $integrationService
     * @param OauthServiceInterface $oauthService
     * @param EncryptorInterface $encryptor
     * @param LoggerInterface $logger
     * @param SchemaSetupInterface $schemaSetup
     */
    public function __construct(
        IntegrationServiceInterface $integrationService,
        OauthServiceInterface $oauthService,
        EncryptorInterface $encryptor,
        LoggerInterface $logger,
        SchemaSetupInterface $schemaSetup
    ) {
        $this->integrationService = $integrationService;
        $this->oauthService = $oauthService;
        $this->encryptor = $encryptor;
        $this->logger = $logger;
        $this->schemaSetup = $schemaSetup;
    }


    private function deleteExistingClientAuthDetails($connection, $tableName, $consumerKey): bool
    {
        // Fetch existing entries with the same consumer_key
        $existingEntries = $connection->fetchAll(
            "SELECT * FROM $tableName WHERE consumer_key = :consumer_key",
            ['consumer_key' => $consumerKey]
        );

        // Deactivate all existing entries with the same consumer_key
        foreach ($existingEntries as $entry) {
            $connection->update(
                $tableName,
                ['is_active' => false],
                [
                    'consumer_key = ?' => $consumerKey,
                    'is_active = ?' => true,
                ]
            );
        }

        // Check if any active entries still exist
        $activeEntries = $connection->fetchAll(
            "SELECT * FROM $tableName WHERE consumer_key = :consumer_key AND is_active = 1",
            ['consumer_key' => $consumerKey]
        );
    
        return !empty($activeEntries);
    }


    /**
     * @param SBOauthClientInterface $sbOAuthClient
     * @return object
     */
    public function insertUpdateClientAuthDetails(string $sbIntegrationClientUUID, string $consumerKey, string $sbAccessToken, string $sbSvixAccessToken, string $sbSvixAppId, bool $isActive): ApiResponse
    {
        try {
            // Get the Magento OAuth Consumer Id
            $consumer = $this->oauthService->loadConsumerByKey($consumerKey);
            $consumerId = $consumer->getId();
            if (!$consumerId) {
                return new ApiResponse(true, "Cannot find consumer key :: ");
            }

            // Get the Magento Integration Id
            $integration = $this->integrationService->findByConsumerId($consumerId);
            $integrationId = $integration->getId();
            if (!$integrationId) {
                return new ApiResponse(true, "Cannot find integration id for consumer key :: ");
            }
        
            $installer = $this->schemaSetup;
            $installer->startSetup();

            if ($installer->tableExists(self::SB_INTEGRATION_AUTH_CLIENT_TABLE)) {
                $tableName = $installer->getTable(self::SB_INTEGRATION_AUTH_CLIENT_TABLE);
                if ($installer->getConnection()->isTableExists($tableName)) {

                    $isActiveDetailPresent = $this->deleteExistingClientAuthDetails(
                        $installer->getConnection(),
                        $tableName,
                        $consumerKey
                    );

                    if ($isActiveDetailPresent) {
                        return new ApiResponse(true, "Error updating existing entry for consumer key :: ");
                    }

                    $item = [
                        'sb_integration_client_uuid'    => $sbIntegrationClientUUID,
                        'integration_id'                => $integrationId,
                        'consumer_key'                  => $consumerKey,
                        'sb_access_token'               => $sbAccessToken,
                        'sb_svix_access_token'          => $sbSvixAccessToken,
                        'sb_svix_app_id'                => $sbSvixAppId,
                        'is_active'                     => $isActive
                    ];
                    $installer->getConnection()->insert($tableName, $item);
                }
         
                $installer->endSetup();
            }

            return new ApiResponse(false, "Inserted Store Auth Details");
        } catch (\Exception $exception) {
            return new ApiResponse(true, "Error in insertUpdateClientAuthDetails :: err :: " . $exception->getMessage());
        }
    }

    public function getActiveClientIntegrationAuthDetails(): ApiResponse
    {
        try {
            $installer = $this->schemaSetup;
            $installer->startSetup();
            if ($installer->tableExists(self::SB_INTEGRATION_AUTH_CLIENT_TABLE)) {
                $tableName = $installer->getTable(self::SB_INTEGRATION_AUTH_CLIENT_TABLE);
                $connection = $installer->getConnection();
                $activeEntries = $connection->fetchAll(
                    "SELECT * FROM $tableName WHERE is_active = 1"
                );
                
                if (empty($activeEntries)) {
                    return new ApiResponse(true, 'No active entries found for Integration Auth Details');
                }
                $activeEntry = $activeEntries[0];
                $installer->endSetup();
                return new ApiResponse(false, 'First active entry retrieved successfully', $activeEntry);
            }
            $installer->endSetup();
            return new ApiResponse(true, 'Table not found with active entries for Integration Auth Details');
        } catch (\Exception $exception) {
            $this->logger->info("Error in getActiveClientIntegrationAuthDetails ::  err :: " . $exception->getMessage());
            return new ApiResponse(true, "Error in getActiveClientIntegrationAuthDetails ::  err :: " . $exception->getMessage());
        }
    }
}