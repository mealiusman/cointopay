<?php

/**
* Copyright © 2018 Cointopay. All rights reserved.
* See COPYING.txt for license details.
* @author Ali Usman <mealiusman@gmail.com>
*/

namespace Cointopay\Paymentgateway\Controller\Index;

class Index extends \Magento\Framework\App\Action\Action
{
    protected $_context;
    protected $_pageFactory;
    protected $_jsonEncoder;
    protected $_coreSession;
    protected $resultJsonFactory;
    /**
   * @var \Magento\Framework\App\Config\ScopeConfigInterface
   */
   protected $scopeConfig;

    /**
    * @var \Magento\Framework\HTTP\Client\Curl
    */
    protected $_curl;

    /**
    * @var $merchantId
    **/
    protected $merchantId;

    /**
    * @var $merchantKey
    **/
    protected $merchantKey;

    /**
    * @var $coinId
    **/
    protected $coinId;

    /**
    * @var $type
    **/
    protected $type;

    /**
    * @var $orderTotal
    **/
    protected $orderTotal;

    /**
    * @var $_curlUrl
    **/
    protected $_curlUrl;

    /**
    * @var currencyCode
    **/
    protected $currencyCode;

    /**
    * @var $_storeManager
    **/
    protected $_storeManager;
    
    /**
    * @var $securityKey
    **/
    protected $securityKey;

    /**
    * Merchant ID
    */
    const XML_PATH_MERCHANT_ID = 'payment/cointopay_gateway/merchant_gateway_id';

    /**
    * Merchant COINTOPAY API Key
    */
    const XML_PATH_MERCHANT_KEY = 'payment/cointopay_gateway/merchant_gateway_key';

    /**
    * Merchant COINTOPAY SECURITY Key
    */
    const XML_PATH_MERCHANT_SECURITY = 'payment/cointopay_gateway/merchant_gateway_security';

    /**
    * API URL
    **/
    const COIN_TO_PAY_API = 'https://cointopay.com/MerchantAPI';

    /**
    * @var $response
    **/
    protected $response = [] ;

    /**
    * @var \Magento\Framework\Registry
    */
    protected $_registry;

    /*
    * @param \Magento\Framework\App\Action\Context $context
    * @param \Magento\Framework\Json\EncoderInterface $encoder
    * @param \Magento\Framework\HTTP\Client\Curl $curl
    * @param \Magento\Framework\App\Config\ScopeConfigInterface    $scopeConfig
    * @param \Magento\Store\Model\StoreManagerInterface $storeManager
    * @param \Magento\Framework\View\Result\PageFactory $pageFactory
    * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
    * @param \Magento\Framework\Registry $registry
    */
    public function __construct (
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\Json\EncoderInterface $encoder,
        \Magento\Framework\HTTP\Client\Curl $curl,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\View\Result\PageFactory $pageFactory,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Framework\Session\SessionManagerInterface $coreSession,
        \Magento\Framework\Registry $registry
    ) {
        $this->_context = $context;
        $this->_jsonEncoder = $encoder;
        $this->_curl = $curl;
        $this->scopeConfig = $scopeConfig;
        $this->_storeManager = $storeManager;
        $this->_pageFactory = $pageFactory;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->_coreSession = $coreSession;
        $this->_registry = $registry;
        parent::__construct($context);
    }

    public function execute()
    {
        if ($this->getRequest()->isXmlHttpRequest()) {
            $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
            $this->coinId = $this->getRequest()->getParam('paymentaction');
            $this->merchantId = trim($this->scopeConfig->getValue(self::XML_PATH_MERCHANT_ID, $storeScope));
            $this->securityKey = trim($this->scopeConfig->getValue(self::XML_PATH_MERCHANT_SECURITY, $storeScope));
            $type = $this->getRequest()->getParam('type');
            $this->currencyCode = $this->_storeManager->getStore()->getCurrentCurrency()->getCode();
            if ($type == 'status') {
                $response = $this->getStatus($this->coinId);
                /** @var \Magento\Framework\Controller\Result\Json $result */
                $result = $this->resultJsonFactory->create();
                return $result->setData(['status' => $response]);
            } else {
                $this->_coreSession->start();
                $this->_coreSession->setCoinid($this->coinId);
                $isVerified = $this->verifyOrder();
                /** @var \Magento\Framework\Controller\Result\Json $result */
                $result = $this->resultJsonFactory->create();
                if ($isVerified == 'success') {
                    return $result->setData(['status' => 'success', 'coindid' => $this->coinId]);
                } else {
                    return $result->setData(['status' => 'error' , 'message' => $isVerified]);
                }
            }
        }
        return;
    }

    /**
    * @return json response
    **/
    private function payOrder() {
        $this->orderTotal = $this->getCartAmount();
        $this->_curlUrl = 'https://cointopay.com/MerchantAPI?Checkout=true&MerchantID='.$this->merchantId.'&Amount='.$this->orderTotal.'&AltCoinID='.$this->coinId.'&CustomerReferenceNr=buy%20something%20from%20me&SecurityCode='.$this->securityKey.'&output=json&inputCurrency='.$this->currencyCode;
        $this->_curl->get($this->_curlUrl);
        $response = $this->_curl->getBody();
        /** @var \Magento\Framework\Controller\Result\Json $result */
        $result = $this->resultJsonFactory->create();
        return $result->setData($response);
    }

    /**
    * @return Total order amount from cart
    **/
    private function getCartAmount () {
        
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $cart = $objectManager->get('\Magento\Checkout\Model\Cart');   
        return $cart->getQuote()->getGrandTotal();
    }

    /**
    * @return string payment status
    **/
    private function getStatus ($TransactionID) {
        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        $this->merchantId = trim($this->scopeConfig->getValue(self::XML_PATH_MERCHANT_ID, $storeScope));
        $this->_curlUrl = 'https://cointopay.com/CloneMasterTransaction?MerchantID='.$this->merchantId.'&TransactionID='.$TransactionID.'&output=json';
        $this->_curl->get($this->_curlUrl);
        $response = $this->_curl->getBody();
        $decoded = json_decode($response);
        return $decoded[1];
    }

    // verify that if order can be placed or not
    private function verifyOrder () {
        $this->orderTotal = $this->getCartAmount();
        $this->_curlUrl = 'https://cointopay.com/MerchantAPI?Checkout=true&MerchantID='.$this->merchantId.'&Amount='.$this->orderTotal.'&AltCoinID='.$this->coinId.'&CustomerReferenceNr=buy%20something%20from%20me&SecurityCode='.$this->securityKey.'&output=json&inputCurrency='.$this->currencyCode.'&testcheckout';
        $this->_curl->get($this->_curlUrl);
        $response = $this->_curl->getBody();
        if ($response == '"testcheckout success"') {
            return 'success';
        }
        return $response;
    }
}