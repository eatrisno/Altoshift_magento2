<?php
namespace Altoshift\Magento\Controller\Feed;

use \Magento\CatalogInventory\Api\StockRegistryInterface;

class Index extends \Magento\Framework\App\Action\Action
{
	protected $_pageFactory;
	protected $_productCollectionFactory;
	protected $_dataHelper;
	protected $_storeManager;
	protected $_appEmulation;
	protected $_blockFactory;
	private $products;
	private $categories;
	private $feed_field = array(
		'entity_id' => 'id',
		'name' => 'title',
		'description' => 'description',
		'price' => 'price',
		'special_price' => 'sale_price'
		);
	public $additionalFields = [];
	// private $includedFields = array("entity_id", "name", "description", "price", "special_price", "availability");
	private $includedFields = array("id");
	public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Magento\Framework\View\Result\PageFactory $pageFactory,
		\Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \Altoshift\Magento\Helper\Data $dataHelper,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\View\Element\BlockFactory $blockFactory,
        \Magento\Store\Model\App\Emulation $appEmulation
		)
	{
		$this->_productCollectionFactory = $productCollectionFactory;    
		$this->_pageFactory = $pageFactory;
		$this->_dataHelper = $dataHelper;
		$this->_storeManager = $storeManager;
		$this->_blockFactory = $blockFactory;
		$this->_appEmulation = $appEmulation;
		return parent::__construct($context);
	}

	public $header = [];

	protected function getImageUrl($product, string $imageType = '')
	{
		$storeId = $this->_storeManager->getStore()->getId();

		$this->_appEmulation->startEnvironmentEmulation($storeId, \Magento\Framework\App\Area::AREA_FRONTEND, true);

		$imageBlock = $this->_blockFactory->createBlock('Magento\Catalog\Block\Product\ListProduct');
		$productImage = $imageBlock->getImage($product, $imageType);
		$imageUrl = $productImage->getImageUrl();
		$this->_appEmulation->stopEnvironmentEmulation();
		return $imageUrl;
	}

	private function createElement($name, $value)
    {
        echo '<' . $name . '>';
        $this->wrapCdata($value);
        echo '</' . $name . '>';
    }
	private function wrapCdata($value)
    {
        echo "<![CDATA[$value]]>";
	}

	private function getCategoryTree($categories) {
		$result = [];
		foreach ($categories as $category) {
			$parentCategories = $category->getParentCategories();
			$childrenCategories = $category->getChildrenCategories();
			foreach ($parentCategories as $parentCategory) {
				$result[] = $parentCategory->getId();
			}
			$result[] = $category->getId();
			foreach ($childrenCategories as $childrenCategory) {
				$result[] = $childrenCategory->getId();
			}
		}
		return $result;
	}

    private function getListCategory(){
    	$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
    	$categories = $objectManager->get('\Magento\Catalog\Model\ResourceModel\Category\CollectionFactory')->create();
		$categories->addAttributeToSelect('*');
		// $result = [];
		foreach ($categories as $category) {
			$temp = array(
				"id" => $category->getId(),
				"name" => $category->getName(),
				"url" => $category->getUrl(),
				"parent" => $category->getParentId()
			);
			$this->categories[] = $temp;
		}
		// return $result;
    }

    private function loadHeader() {
    	$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
    	$_page_config = $objectManager->get('Magento\Framework\View\Page\Config');
    	$this->header['title'] = $this->_storeManager->getStore()->getName();
    	// $this->header['title2'] = strip_tags($_page_config->getTitle()->getShort());
    	$this->header['link'] = $this->_storeManager->getStore()->getBaseUrl();
    	$this->header['description'] = strip_tags($_page_config->getDescription());
    	// $this->header['description'] = $this->_storeManager->getStore()->getCode();
    	// $this->header['description'] = $this->_storeManager->getStore()->getWebsiteDescription();
    }

    private function productToArray($product) {
    	$tempProduct = [];
    	$flag = count($this->additionalFields);//Flag to check if additionalFields are already filled
    	foreach($product->getData() as $field => $value){
			// if(!in_array($field, $this->includedFields)) {
    		$key = array_key_exists($field, $this->feed_field) ? $this->feed_field[$field] : $field;
    		if ($flag == 0) {
    			$this->additionalFields[] = $key;
    		}
			$tempProduct[$key] = $value;
			// }
		}
		$categories = $product->getCategoryCollection();
		$tempAdditionalField = ["availability", "categoryIds", "link", "image_link", "categoryTree"];
		if (!array_key_exists("sale_price", $tempProduct)) {
			$tempAdditionalField[] = "sale_price";
    		$tempProduct["sale_price"] = -1;
    	}
    	if (!array_key_exists("price", $tempProduct)) {
			$tempAdditionalField[] = "price";
    		$tempProduct["price"] = -1;
    	}
    	if (!array_key_exists("description", $tempProduct)) {
			$tempAdditionalField[] = "description";
    		$tempProduct["description"] = "";
    	}
		if ($flag == 0) {
			$this->additionalFields = array_merge($tempAdditionalField, $this->additionalFields);
		}
    	$tempProduct["availability"] = $product->get_stock_status() == 1 ? "in stock" : "out of stock";
    	$tempProduct["categoryIds"] = $categories;
    	$tempProduct["categoryTree"] = $this->getCategoryTree($categories);
    	$tempProduct["link"] = $product->getProductUrl();
    	/*
		In getImageUrl($product, $thisParam), we can change $thisParam base on here
		https://github.com/magento/magento2/blob/732d445fe68b2c00a03dda546e3813dea04d441e/app/design/frontend/Magento/luma/etc/view.xml
		*/
    	$tempProduct["image_link"] = $this->getImageUrl($product, 'category_page_list');
    	return $tempProduct;
    }

    private function loadProduct() {
    	// Get additionalFields from query param
    	if (isset($_GET['more_fields']) && !empty($_GET['more_fields'])) {
            try {
                $moreFields = explode(",", $_GET['more_fields']);
                $this->includedFields = array_merge($this->includedFields, $moreFields);
            } catch(Exception $e) {
                $moreFields = array();
            }
        }

        //Variable that used to convert original field to our field name
    	$collection = $this->_productCollectionFactory->create();
		$collection->setFlag('has_stock_status_filter', true);
		$collection = $collection->joinField('qty',
			'cataloginventory_stock_item',
			'qty',
			'product_id=entity_id',
			'{{table}}.stock_id=1',
			'left')->joinTable('cataloginventory_stock_item', 'product_id=entity_id', array('stock_status' => 'is_in_stock'))->addAttributeToSelect('*')->addAttributeToSelect('stock_status')->setOrder('created_at', 'desc');
		//Set pagination
		if (isset($_GET['limit']) && !empty($_GET['limit'])) {
			$collection->setPageSize($_GET['limit']);
		}
		if (isset($_GET['skip']) && !empty($_GET['skip']) && isset($_GET['limit']) && !empty($_GET['limit'])) {
			$page = $_GET['skip']/$_GET['limit'] + 1;
			$collection->setCurPage($page);
		}

		foreach ($collection as $product) {			
			$tempProduct = $this->productToArray($product);
			$resultProduct = [];
			foreach ($this->includedFields as $field) {
				if (array_key_exists($field, $tempProduct)) {
					$resultProduct[$field] = $tempProduct[$field];
				} else {
					$resultProduct[$field] = "";
				}
			}
			$this->products[] = $resultProduct;
		}
    }

    private function render() {
    	Header('Content-type: text/xml');	
		echo '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel>';	

		// echo "<header>";
		foreach ($this->header as $headerName => $headerValue) {
            $this->createElement($headerName, $headerValue);
        }
        // echo "</header>";

		echo '<allFields>';
        foreach($this->additionalFields as $field)
        {
            $this->createElement('field', $field);
        }
        echo '</allFields>';

        foreach ($this->categories as $category) {
        	echo '<category>';
        	foreach ($category as $key => $value) {
	        	$this->createElement($key, $value);
	        }
	        echo '</category>';
        }
        foreach ($this->products as $product) {
        	echo "<item>";
        	foreach ($product as $key => $value) {
	        	if ($key === "categoryIds") {
	        		echo "<categoryIds>";
	        		foreach ($value as $category) {
	        			$this->createElement("category", $category->getId());
	        			// $this->createElement("category", $category);
	        		}
	        		echo "</categoryIds>";
	        		continue;
	        	}
	        	if ($key === "categoryTree") {
	        		echo "<categoryTree>";
	        		foreach ($value as $category) {
	        			$this->createElement("category", $category);
	        		}
	        		echo "</categoryTree>";
	        		continue;
	        	}
	        	$this->createElement($key, $value);
	        }
	        echo "</item>";
        }
		echo '</channel></rss>';
    }
	
	public function execute()
	{
		$password_enable = $this->_dataHelper->getFeedConfig('password_enable');
		$password = $this->_dataHelper->getFeedConfig('password');
		$secret = '';
		$this->getListCategory();
		$this->loadHeader();

		if (isset($_GET['secret'])){ 
			$secret = $_GET['secret']; 
		}
		if($password_enable){
			if($secret != $password){
				exit('You need the correct password to access this page');
			}
		}

		$this->loadProduct(); //load all of the products

		$this->render(); //render xml page
		
		exit;
	}
}