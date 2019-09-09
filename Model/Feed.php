<?php

namespace Altoshift\Magento\Model;

class Feed implements \Magento\Config\Model\Config\CommentInterface
{
	protected $_dataHelper;
	protected $_storeManager;
	public function __construct(
        \Altoshift\Magento\Helper\Data $dataHelper,
        \Magento\Store\Model\StoreManagerInterface $storeManager
		)
	{
		$this->_dataHelper = $dataHelper;
		$this->_storeManager = $storeManager;
	}
    public function getCommentText($elementValue)
    {
    	$result = $this->_storeManager->getStore()->getBaseUrl()."altoshift/feed";
    	$password_enable = $this->_dataHelper->getFeedConfig('password_enable');
		$password = $this->_dataHelper->getFeedConfig('password');
		if($password_enable){
			$result = $result . "?secret=" . $password;
		}
        return $result;
    }
}
