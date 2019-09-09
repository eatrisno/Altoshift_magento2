<?php
namespace Altoshift\Magento\Block;

class Layer extends \Magento\Framework\View\Element\Html\Link
{
    protected $_registry;
    protected $_dataHelper;

    public function __construct(
		\Magento\Framework\View\Element\Template\Context $context,
        \Altoshift\Magento\Helper\Data $dataHelper,
        \Magento\Framework\Registry $registry
        
	){
		parent::__construct($context);
        $this->_dataHelper = $dataHelper;
        $this->_registry = $registry;
    }
    
    public function getProduct()
    {
        return $this->getItem()->getProduct();
    }

    public function getLayerScript()
	{
        $enable = $this->_dataHelper->getLayerConfig('enable');
		$script = $this->_dataHelper->getLayerConfig('script');
        if ($enable == "1"){
            return $script;
        }
        return;
    }

    public function addProductIdMetaTag()
    {
        $product = $this->_registry->registry('current_product');
        if ($product !== null){
            $productId = $product->getId();
            return "<meta name=\"productId\" content=\"".$productId."\" />";
        }
        return;
    }
}
