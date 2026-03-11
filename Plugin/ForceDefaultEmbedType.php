<?php
namespace Dintero\HyvaCheckout\Plugin;

class ForceDefaultEmbedType
{
    public function afterGetEmbedType()
    {
        return \Dintero\Checkout\Model\Api\Client::TYPE_EMBEDDED;
    }
}
