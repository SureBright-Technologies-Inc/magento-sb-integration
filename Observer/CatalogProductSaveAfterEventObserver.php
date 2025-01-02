<?php


namespace Surebright\Integration\Observer;

use Surebright\Integration\Service\SBEventsDispatchService;
use Magento\Framework\Event\Observer;
use Psr\Log\LoggerInterface;

class CatalogProductSaveAfterEventObserver implements \Magento\Framework\Event\ObserverInterface
{
    private $sbEventsDispatchService;
    private LoggerInterface $logger;

    public function __construct(
        SBEventsDispatchService $sbEventsDispatchService,
        LoggerInterface $logger
    ) {
        $this->sbEventsDispatchService = $sbEventsDispatchService;
        $this->logger = $logger;
    }


    public function execute(Observer $observer)
    {
        try {
            $product = $observer->getEvent()->getProduct();
            $productId = $product->getId();
            $productSku = $product->getSku();
            $eventPayload = ['productId' => $productId, 'productSku' => $productSku, "sbEventType" => "product_create_update"];

            $body = ['data' => $eventPayload];
            $this->sbEventsDispatchService->dispatch($body);
        } catch (\Exception $exception) {
            $this->logger->info("Error in CatalogProductSaveAfterEventObserver :: execute ::  err :: " . $exception->getMessage());
        }
    }
}