<?php

namespace Surebright\Integration\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\View\LayoutInterface;
use Magento\Framework\View\Design\Theme\ThemeProviderInterface;
use Magento\Framework\Module\Manager as ModuleManager;
use Surebright\Integration\Helper\SureBrightLogger;

class ViewFileLogger implements ObserverInterface
{
    protected $surebrightLogger;
    protected $themeProvider;
    protected $moduleManager;

    public function __construct(
      SureBrightLogger $surebrightLogger,
        ThemeProviderInterface $themeProvider,
        ModuleManager $moduleManager
    ) {
        $this->surebrightLogger = $surebrightLogger;
        $this->themeProvider = $themeProvider;
        $this->moduleManager = $moduleManager;
    }

    /**
     * Observer execute method
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        try {
            /** @var LayoutInterface $layout */
            $layout = $observer->getLayout();
            $eventName = $observer->getEvent()->getName();

             // Check if module is active
             if (!$this->moduleManager->isEnabled('Surebright_Integration')) {
                  $this->surebrightLogger->logInstallationStep(
                      'ViewFileLogger.php',
                      "Module Inactive",
                      ["moduleName" => 'Surebright_Integration', "eventName" => $eventName]
                  );
                  return;
              }else{
                $this->surebrightLogger->logInstallationStep('ViewFileLogger.php','Module Active',["eventName" => $eventName]);
              }

           if($layout){
             // Log active layout handles
             $handles = $layout->getUpdate()->getHandles();
             $this->surebrightLogger->logInstallationStep(
                 'ViewFileLogger.php',
                 "Layout Handles Retrieved",
                 ["handles" => $handles, "eventName" => $eventName]
             );
 
             // Validate custom handle
             if (!in_array('sb.script.footer', $handles)) {
                 $this->surebrightLogger->logInstallationStep(
                     'ViewFileLogger.php',
                     "Custom handle 'sb.script.footer' not found.",
                     ["handles" => $handles, "eventName" => $eventName]
                 );
             }

              // Check loaded template paths
              $allBlocks = $layout->getAllBlocks();
              $loadedTemplates = [];
              foreach ($allBlocks as $blockName => $blockObj) {
                  $loadedTemplates[] = [
                      'name' => $blockName,
                      'template' => $blockObj->getTemplate()
                  ];
              }
              $this->surebrightLogger->logInstallationStep('ViewFileLogger', 'Loaded templates:', $loadedTemplates);
          
              // Verify layout handles
              $handles = $layout->getUpdate()->getHandles();
              $this->surebrightLogger->logInstallationStep('ViewFileLogger', 'Current Layout Handles:', $handles);
 
             // Check block rendering
             $block = $layout->getBlock('sb.script.footer');
             if ($block) {
                 $templateFile = $block->getTemplateFile();
                 $renderedContent = $block->toHtml();
 
                 $this->surebrightLogger->logInstallationStep(
                     'ViewFileLogger.php',
                     "Block Rendering",
                     ["templateFile" => $templateFile, "renderedContent" => $renderedContent, "eventName" => $eventName]
                 );
 
                 if (empty($renderedContent)) {
                     $this->surebrightLogger->logInstallationStep(
                         'ViewFileLogger.php',
                         "Block rendering failed",
                         ["blockName" => $block->getNameInLayout(), "eventName" => $eventName]
                     );
                 }
             } else {
                 $this->surebrightLogger->logInstallationStep(
                     'ViewFileLogger.php',
                     "Block 'sb.script.footer' not found",
                     ["eventName" => $eventName]
                 );
             }
 
             // Check head.additional block
             $headBlock = $layout->getBlock('head.additional');
             if ($headBlock) {
                 $this->surebrightLogger->logInstallationStep(
                     'ViewFileLogger.php',
                     "head.additional Block Found",
                     ["content" => $headBlock->toHtml(), "eventName" => $eventName  ]
                 );
             } else {
                 $this->surebrightLogger->logInstallationStep(
                     'ViewFileLogger.php',
                     "head.additional Block Not Found",
                     ["eventName" => $eventName]
                 );
             }
           }else{
            $this->surebrightLogger->logInstallationStep('ViewFileLogger.php','Layout not found',["eventName" => $eventName]);
           }

        } catch (\Exception $e) {
            $this->surebrightLogger->logInstallationStep(
                'ViewFileLogger.php',
                "Error in ViewFileLogger",
                ["errorMessage" => $e->getMessage(), "eventName" => $eventName]
            );
        }
    }
}
