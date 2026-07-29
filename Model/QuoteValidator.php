<?php

namespace Dintero\HyvaCheckout\Model;

use Dintero\Checkout\Model\Dintero as DinteroPaymentMethod;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Validator as QuoteAddressValidator;
use Magento\Quote\Model\SubmitQuoteValidator;

class QuoteValidator
{
    /**
     * @var SubmitQuoteValidator $submitQuoteValidator
     */
    private $submitQuoteValidator;

    /**
     * @var QuoteAddressValidator $quoteAddressValidator
     */
    private $quoteAddressValidator;

    /**
     * Define class dependencies
     *
     * @param SubmitQuoteValidator $submitQuoteValidator
     * @param QuoteAddressValidator $quoteAddressValidator
     */
    public function __construct(
        SubmitQuoteValidator $submitQuoteValidator,
        QuoteAddressValidator $quoteAddressValidator
    ) {
        $this->submitQuoteValidator = $submitQuoteValidator;
        $this->quoteAddressValidator = $quoteAddressValidator;
    }

    /**
     * Verify that the quote is complete enough to be paid for
     *
     * @param Quote $quote
     * @return void
     * @throws LocalizedException
     */
    public function validate(Quote $quote)
    {
        $this->submitQuoteValidator->validateQuote($quote);

        if ($quote->getPayment()->getMethod() !== DinteroPaymentMethod::METHOD_CODE) {
            throw new LocalizedException(__('Invalid payment method.'));
        }

        $addresses = [$quote->getBillingAddress()];

        if (!$quote->isVirtual()) {
            if (!$quote->getShippingAddress()->getShippingMethod()) {
                throw new LocalizedException(__('Shipping method is required.'));
            }

            $addresses = array_merge($addresses, $quote->getAllShippingAddresses());
        }

        foreach ($addresses as $address) {
            $this->quoteAddressValidator->isValid($address);

            if ($this->quoteAddressValidator->hasMessages()) {
                throw new LocalizedException(__(
                    'Invalid address: %1',
                    implode('<br/>', $this->quoteAddressValidator->getMessages())
                ));
            }
        }
    }
}
