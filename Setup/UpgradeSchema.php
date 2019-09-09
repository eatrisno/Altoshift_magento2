<?php
namespace Altoshift\Magento\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Altoshift\Magento\Block\Onepage\Success;

class UpgradeSchema implements UpgradeSchemaInterface
{
    protected $_storeManager;
    protected $_observer;
    protected $_store;
    const STATS_ENDPOINT = 'https://api.altoshift.com/statsendpoint/stats';

    public function __construct(
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Store\Api\Data\StoreInterface $store,
        \Magento\Framework\Event\Observer $observer
    ) {
        $this->_observer = $observer;
        $this->_storeManager = $storeManager;
        $this->_store = $store;
    }

    public static function sendPost($url, $data )
    {
        $fields_string = http_build_query($data);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
        $result = curl_exec($ch);
    }

    public static function postStats($data)
    {
        self::sendPost(self::STATS_ENDPOINT, $data);
    }

    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context ) {
        $installer = $setup;
        $installer->startSetup();
        $version = '0.0.0';
        $serverIp =getHostByName(getHostName()); // return 127.0.0.1 because run from terminal
        if($context->getVersion()) {
           $version = $context->getVersion(); 
        }
        try {
            self::postStats(array(
                'event' => 'pluginInstall',
                'data' => array(
                    'pluginVersion' => $version,
                    'host' => $this->_storeManager->getStore()->getBaseUrl(),
                    'ip' => $serverIp,
                    'locale' => $this->_store->getLocaleCode()
                )
            ));
        } catch (Exception $e) {
        }


        $installer->endSetup();
    }

}   