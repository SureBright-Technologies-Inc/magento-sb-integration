<?php
namespace Surebright\Integration\Model;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\DataObject\IdentityInterface;
use Surebright\Integration\Model\ResourceModel\SureBrightLogger as SureBrightLoggerResource;

class SureBrightLogger extends AbstractModel implements IdentityInterface
{
    protected function _construct()
    {
        $this->_init(SureBrightLoggerResource::class);
    }

    public function getIdentities()
    {
        return ['surebright_logger_' . $this->getId()];
    }
}
