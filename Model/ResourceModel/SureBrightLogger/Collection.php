<?php
namespace Surebright\Integration\Model\ResourceModel\SureBrightLogger;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Surebright\Integration\Model\SureBrightLogger;
use Surebright\Integration\Model\ResourceModel\SureBrightLogger as SureBrightLoggerResource;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(SureBrightLogger::class, SureBrightLoggerResource::class);
    }
}
