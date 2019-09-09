<?php
namespace Altoshift\Magento\Block\Onepage;

class Success extends \Magento\Sales\Block\Order\Totals
{
    protected $checkoutSession;
    protected $customerSession;
    protected $observer;
    protected $_orderFactory;
    protected $_dataHelper;
    const STATS_ENDPOINT = 'https://api.altoshift.com/statsendpoint/stats';

    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Altoshift\Magento\Helper\Data $dataHelper,
        \Magento\Framework\Event\Observer $observer,
        array $data = []
    ) {
        parent::__construct($context, $registry, $data);
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->_orderFactory = $orderFactory;
        $this->_dataHelper = $dataHelper;
        $this->observer = $observer;
    }

    public static function sendPost($url, $data )
    {
        $fields_string = http_build_query($data);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_POST, 1);
        // Edit: prior variable $postFields should be $postfields;
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); // On dev server only!
        $result = curl_exec($ch);
    }

    public function getOrder()
    {
        return  $this->_order = $this->_orderFactory->create()->loadByIncrementId(
            $this->checkoutSession->getLastRealOrderId());
    }

    public function getOrderDetails($entity_id)
    {
        $order = $this->_orderFactory->create()->load($entity_id);
        $orderItems = $order->getAllItems();
        return $orderItems;
    }

    public static function postStats($data)
    {
        self::sendPost(self::STATS_ENDPOINT, $data);
    }

    public static function getClickedProductsFromCookies($engineToken)
    {
        $key = 'als-' . $engineToken;
        if (!isset($_COOKIE[$key])) {
            return array();
        }
        return json_decode(stripslashes($_COOKIE[$key]), true);
    }

    public static function setCookieMonster($engineToken)
    {
        $cookie_name = 'als-' . $engineToken;
        $cookie_value = '{"sessionId":"1eg58ynby-1320063955jtgzhqo1","timeZone":"Asia/Jakarta","searchProducts":[{"searchId":"dbc33842338124aee7c2b8b255b8d640","productId":"2"},{"searchId":"a0ad971046fc911f23f9ef421266cef7","productId":"27780"},{"searchId":"89f6c27fcb07bddbd7074e59cbd63f2e","productId":"1"}]}';
        setcookie($cookie_name, $cookie_value, time() + (86400 * 30), "/");
    }

    public function onCheckout() 
    {
        try{
            $sendStats = $this->_dataHelper->getAnalyticConfig('send_checkout_stats');
            if ($sendStats == "1"){
                $engineToken = $this->_dataHelper->getSettingConfig('engine_token');
                // $this->setCookieMonster($engineToken);
                $clicksData = self::getClickedProductsFromCookies($engineToken);
                if (!count($clicksData)) {
                    return;
                }
                $cartProductIds = array();
                $orderProducts = $this->getOrder();
                $orderProductDetails = $this->getOrderDetails($orderProducts['entity_id']);
                
                foreach ($orderProductDetails as $item) {
                    $cartProductIds[]=$item->getProductId();
                }

                $checkoutStatsPayload = array();
                $sessionId = $clicksData['sessionId'];
                $currentUserAgent = $_SERVER['HTTP_USER_AGENT'];
                foreach ($clicksData['searchProducts'] as $product) {
                    if (in_array($product['productId'], $cartProductIds)) {
                        $checkoutStatsPayload[] = array(
                            'searchId' => $product['searchId'],
                            'productId' => $product['productId'],
                            'sessionId' => $sessionId
                        );
                    }
                }

                if (!count($checkoutStatsPayload)) {
                    return;
                }

                self::postStats(array(
                    'event' => 'checkout',
                    'data' => array(
                        'engineToken' => $engineToken,
                        'userAgent' => $currentUserAgent,
                        'products' => $checkoutStatsPayload
                    )
                ));
            }
        } catch (Exception $e) {
        }
      
    }
}