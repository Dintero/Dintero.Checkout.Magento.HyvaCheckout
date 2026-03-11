<?php

namespace Dintero\HyvaCheckout\Block;

use Dintero\Checkout\Helper\Config as ConfigHelper;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class Checkout extends Template
{

    private $configHelper;

    /**
     * Define class dependencies
     *
     * @param Context $context
     * @param ConfigHelper $config
     * @param array $data
     */
    public function __construct(
        Context $context,
        ConfigHelper $configHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->configHelper = $configHelper;
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
            'language'          => $this->configHelper->getLanguage(),
        ]);
        return $this->toJson([
            'enabled',
            'isEmbedded',
            'isExpress',
            'isPopout',
            'language'
        ]);
    }
}
