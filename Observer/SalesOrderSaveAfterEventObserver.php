<?php


namespace Surebright\Integration\Observer;

use Surebright\Integration\Service\SBEventsDispatchService;
use Magento\Framework\Event\Observer;
use Psr\Log\LoggerInterface;

class SalesOrderSaveAfterEventObserver implements \Magento\Framework\Event\ObserverInterface
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
            $order = $observer->getEvent()->getOrder();
            $orderId = $order->getId();
            $orderStatus = $order->getStatus();
            $eventPayload = ['orderId' => $orderId, 'orderStatus' => $orderStatus, "sbEventType" => "order_create_update"];
            $body = [
                "eventType" => "sales_order_save_after",
                "payload" => $eventPayload
            ];

            $this->sbEventsDispatchService->dispatch($body);
        } catch (\Exception $exception) {
            $this->logger->info("Error in SalesOrderSaveAfterEventObserver :: execute ::  err :: " . $exception->getMessage());
        }
    }
}