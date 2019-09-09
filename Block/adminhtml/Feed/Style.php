<?php
namespace Altoshift\Magento\Block\Adminhtml\Feed;

use Magento\Framework\Data\Form\Element\AbstractElement;

class Style extends \Magento\Config\Block\System\Config\Form\Field
{    
    protected function _getElementHtml(AbstractElement $element)
    {
        $element->setDisabled('readonly', 1);
        return $element->getElementHtml();

    }
}