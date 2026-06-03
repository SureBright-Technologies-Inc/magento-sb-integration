<?php

namespace Surebright\Integration\Service;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\InventoryApi\Api\SourceRepositoryInterface;
use Surebright\Integration\Api\SBOAuthClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Runs a representative read operation for each access group declared in
 * etc/integration/api.xml so a merchant can confirm the integration's
 * permissions actually work end to end.
 *
 * Each test performs a minimal, read-only call (page size 1) against the same
 * repositories the SureBright API consumes and reports success/failure with a
 * short, human-readable result.
 */
class AccessTester
{
    /** @var SearchCriteriaBuilder */
    private $searchCriteriaBuilder;

    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var ProductRepositoryInterface */
    private $productRepository;

    /** @var OrderRepositoryInterface */
    private $orderRepository;

    /** @var CustomerRepositoryInterface */
    private $customerRepository;

    /** @var CartRepositoryInterface */
    private $cartRepository;

    /** @var SourceRepositoryInterface */
    private $sourceRepository;

    /** @var SBOAuthClientInterface */
    private $sbOAuthClient;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        SearchCriteriaBuilder $searchCriteriaBuilder,
        StoreManagerInterface $storeManager,
        ProductRepositoryInterface $productRepository,
        OrderRepositoryInterface $orderRepository,
        CustomerRepositoryInterface $customerRepository,
        CartRepositoryInterface $cartRepository,
        SourceRepositoryInterface $sourceRepository,
        SBOAuthClientInterface $sbOAuthClient,
        LoggerInterface $logger
    ) {
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->storeManager = $storeManager;
        $this->productRepository = $productRepository;
        $this->orderRepository = $orderRepository;
        $this->customerRepository = $customerRepository;
        $this->cartRepository = $cartRepository;
        $this->sourceRepository = $sourceRepository;
        $this->sbOAuthClient = $sbOAuthClient;
        $this->logger = $logger;
    }

    /**
     * Execute the read test for a single access group.
     *
     * @param string $group One of: backend, sales, catalog, quote, customer, inventory, custom
     * @return array{success: bool, message: string}
     */
    public function test(string $group): array
    {
        try {
            switch ($group) {
                case 'backend':
                    return $this->success(sprintf(
                        'Backend access OK — read %d store view(s).',
                        count($this->storeManager->getStores())
                    ));

                case 'sales':
                    return $this->success(sprintf(
                        'Sales access OK — %d order(s) visible.',
                        $this->orderRepository->getList($this->singleResultCriteria())->getTotalCount()
                    ));

                case 'catalog':
                    return $this->success(sprintf(
                        'Catalog access OK — %d product(s) visible.',
                        $this->productRepository->getList($this->singleResultCriteria())->getTotalCount()
                    ));

                case 'quote':
                    return $this->success(sprintf(
                        'Cart & quote access OK — %d quote(s) visible.',
                        $this->cartRepository->getList($this->singleResultCriteria())->getTotalCount()
                    ));

                case 'customer':
                    return $this->success(sprintf(
                        'Customer access OK — %d customer(s) visible.',
                        $this->customerRepository->getList($this->singleResultCriteria())->getTotalCount()
                    ));

                case 'inventory':
                    return $this->success(sprintf(
                        'Inventory access OK — %d inventory source(s) visible.',
                        $this->sourceRepository->getList($this->singleResultCriteria())->getTotalCount()
                    ));

                case 'custom':
                    $decoded = json_decode($this->sbOAuthClient->listIntegrationAuthDetails(), true);
                    if (is_array($decoded) && empty($decoded['isError'])) {
                        $count = isset($decoded['apiPayload']) && is_array($decoded['apiPayload'])
                            ? count($decoded['apiPayload'])
                            : 0;
                        return $this->success(sprintf(
                            'SureBright API OK — integration data readable (%d record(s)).',
                            $count
                        ));
                    }
                    return $this->failure(
                        'SureBright API responded with an error: '
                        . (is_array($decoded) && isset($decoded['message']) ? $decoded['message'] : 'unknown')
                    );

                default:
                    return $this->failure('Unknown access group: ' . $group);
            }
        } catch (\Exception $e) {
            $this->logger->error('SureBright access test [' . $group . ']: ' . $e->getMessage());
            return $this->failure('Access denied or failed: ' . $e->getMessage());
        }
    }

    /**
     * Fresh single-result search criteria for each test.
     *
     * @return \Magento\Framework\Api\SearchCriteriaInterface
     */
    private function singleResultCriteria()
    {
        return $this->searchCriteriaBuilder->setPageSize(1)->setCurrentPage(1)->create();
    }

    /**
     * @param string $message
     * @return array{success: bool, message: string}
     */
    private function success(string $message): array
    {
        return ['success' => true, 'message' => $message];
    }

    /**
     * @param string $message
     * @return array{success: bool, message: string}
     */
    private function failure(string $message): array
    {
        return ['success' => false, 'message' => $message];
    }
}
