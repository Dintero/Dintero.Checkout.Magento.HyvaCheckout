<?php

namespace Dintero\HyvaCheckout\Block;

use Dintero\Checkout\Helper\Config as ConfigHelper;
use Dintero\HyvaCheckout\Model\CheckoutMode;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class Checkout extends Template
{

    /**
     * @var ConfigHelper $configHelper
     */
    private $configHelper;

    /**
     * @var CheckoutMode $checkoutMode
     */
    private $checkoutMode;

    /**
     * Define class dependencies
     *
     * @param Context $context
     * @param ConfigHelper $configHelper
     * @param CheckoutMode $checkoutMode
     * @param array $data
     */
    public function __construct(
        Context $context,
        ConfigHelper $configHelper,
        CheckoutMode $checkoutMode,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->configHelper = $configHelper;
        $this->checkoutMode = $checkoutMode;
    }

    /**
     * Check whether the customer has to be redirected to the Dintero hosted checkout
     *
     * @return bool
     */
    public function isRedirect()
    {
        return $this->checkoutMode->isRedirect();
    }

    /**
     * Retrieve json config
     *
     * @return bool|string
     */
    public function getJsonConfig()
    {
        $this->addData([
            'enabled'           => $this->configHelper->isActive(),
            'placeOrderUrl'     => $this->configHelper->getPlaceOrderUrl(),
            'logoUrl'           => $this->configHelper->getCheckoutLogoUrl(),
            'isEmbedded'        => $this->configHelper->isEmbedded(),
            'isExpress'         => $this->configHelper->isExpress(),
            'isPopout'          => $this->configHelper->isPopOut(),
            'isRedirect'        => $this->isRedirect(),
            'language'          => $this->configHelper->getLanguage(),
        ]);
        return $this->toJson([
            'enabled',
            'isEmbedded',
            'isExpress',
            'isPopout',
            'isRedirect',
            'language'
        ]);
    }
}
