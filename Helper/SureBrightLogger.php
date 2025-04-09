<?php
namespace Surebright\Integration\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Surebright\Integration\Model\SureBrightLoggerFactory;
use Magento\Framework\App\RequestInterface;
use Surebright\Integration\Model\SBOAuthClientRepository;

class SureBrightLogger extends AbstractHelper
{
    protected $loggerFactory;
    protected $request;
    protected $SBOAuthClientRepository;

    public function __construct(Context $context, SureBrightLoggerFactory $loggerFactory, 
    RequestInterface $request, SBOAuthClientRepository $SBOAuthClientRepository)
    {
        parent::__construct($context);
        $this->loggerFactory = $loggerFactory;
        $this->request = $request;
        $this->SBOAuthClientRepository = $SBOAuthClientRepository;
    }

    public function log($domainName, $message,  $context = null, $debugLog = null)
    {
        $log = $this->loggerFactory->create();
        $log->setData([
            'storeId' => $domainName,
            'debugLog' => $debugLog ? json_encode($debugLog) : null,
            'message' => $message,
            'context' => $context
        ]);
        $log->save();
    }

    public function logInstallationStep($context, $message, $details = null, $debugLevel = "log")
    {
        $domain = $this->_urlBuilder->getBaseUrl();
        $parsedUrl = parse_url($domain);
        $domainName = $parsedUrl['host'];
        $isLoggerActive = $this->SBOAuthClientRepository->isLoggerActive();
        if (!$this->request->getParam('sbDebug', false) || ($debugLevel == "log" && !$isLoggerActive)) {
            return;
        }
        $this->log($domainName, $message, $context, $details);
    }
}
