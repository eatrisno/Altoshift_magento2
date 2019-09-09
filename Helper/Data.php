<?php

namespace Altoshift\Magento\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper 
{
	const XML_PATH_ALTOSHIFT = 'altoshift_settings/';

	public function getConfigValue($field, $storeCode = null)
	{
		return $this->scopeConfig->getValue($field, ScopeInterface::SCOPE_STORE, $storeCode);
	}

	public function getLayerConfig($fieldid, $storeCode = null)
	{
		return $this->getConfigValue(self::XML_PATH_ALTOSHIFT. 'layer/' .$fieldid, $storeCode);
	}

	public function getFeedConfig($fieldid, $storeCode = null)
	{
		return $this->getConfigValue(self::XML_PATH_ALTOSHIFT. 'feed/' .$fieldid, $storeCode);
	}

	public function getAnalyticConfig($fieldid, $storeCode = null)
	{
		return $this->getConfigValue(self::XML_PATH_ALTOSHIFT. 'analytic/' .$fieldid, $storeCode);
	}

	public function getSettingConfig($fieldid, $storeCode = null)
	{
		return $this->getConfigValue(self::XML_PATH_ALTOSHIFT. 'setting/' .$fieldid, $storeCode);
	}
}