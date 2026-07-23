<?php

namespace Dintero\HyvaCheckout\Model;

use Dintero\Checkout\Helper\Config as ConfigHelper;

class CheckoutMode
{
    /**
     * @var ConfigHelper $config
     */
    private $config;

    /**
     * Define class dependencies
     *
     * @param ConfigHelper $config
     */
    public function __construct(ConfigHelper $config)
    {
        $this->config = $config;
    }

    /**
     * Check whether the checkout is rendered inside the storefront
     *
     * @return bool
     */
    public function isEmbedded()
    {
        return $this->config->isEmbedded() || $this->config->isEmbeddedExpress();
    }

    /**
     * Check whether the customer has to be redirected to the Dintero hosted checkout
     *
     * @return bool
     */
    public function isRedirect()
    {
        return !$this->isEmbedded();
    }
}
