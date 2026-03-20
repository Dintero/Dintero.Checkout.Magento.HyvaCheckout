<?php
namespace Dintero\HyvaCheckout\Plugin;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class ForceDefaultEmbedType
{
    /**
     * @var ScopeConfigInterface $scopeConfig
     */
    private $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    /*
     * Override embed type
     *
     * @param $subject
     * @param $result
     */
    public function afterGetEmbedType($subject, $result)
    {
        if ($this->scopeConfig->isSetFlag('hyva_theme_fallback/general/enable', ScopeInterface::SCOPE_STORE)) {
            return $result;
        }

        return \Dintero\Checkout\Model\Api\Client::TYPE_EMBEDDED;
    }
}
