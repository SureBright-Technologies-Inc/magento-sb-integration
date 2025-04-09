<?php
namespace Surebright\Integration\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class SureBrightLogger extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('surebrightLogs', 'log_id');
    }
}
