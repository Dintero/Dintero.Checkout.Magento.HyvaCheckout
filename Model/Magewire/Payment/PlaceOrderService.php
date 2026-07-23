<?php

namespace Dintero\HyvaCheckout\Model\Magewire\Payment;

use Dintero\Checkout\Model\Agreements\Validator as AgreementsValidator;
use Dintero\Checkout\Model\Api\Client;
use Dintero\HyvaCheckout\Model\CheckoutMode;
use Dintero\HyvaCheckout\Model\QuoteValidator;
use Hyva\Checkout\Model\Magewire\Component\Evaluation\PlaceOrderRedirect;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultFactory;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultInterface;
use Hyva\Checkout\Model\Magewire\Payment\AbstractOrderData;
use Hyva\Checkout\Model\Magewire\Payment\AbstractPlaceOrderService;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Model\Quote;

/**
 * Produces the Dintero hosted checkout url for the redirect flow.
 *
 * This is the only place the redirect may originate from: it runs after the Hyva place order
 * validation and re-validates the quote server side before a url is handed out.
 */
class PlaceOrderService extends AbstractPlaceOrderService
{
    /**
     * @var Client $client
     */
    private $client;

    /**
     * @var CheckoutMode $checkoutMode
     */
    private $checkoutMode;

    /**
     * @var QuoteValidator $quoteValidator
     */
    private $quoteValidator;

    /**
     * @var AgreementsValidator $agreementsValidator
     */
    private $agreementsValidator;

    /**
     * @var CheckoutSession $checkoutSession
     */
    private $checkoutSession;

    /**
     * @var string|null $redirectUrl
     */
    private $redirectUrl;

    /**
     * Define class dependencies
     *
     * @param CartManagementInterface $cartManagement
     * @param Client $client
     * @param CheckoutMode $checkoutMode
     * @param QuoteValidator $quoteValidator
     * @param AgreementsValidator $agreementsValidator
     * @param CheckoutSession $checkoutSession
     * @param AbstractOrderData|null $orderData
     */
    public function __construct(
        CartManagementInterface $cartManagement,
        Client $client,
        CheckoutMode $checkoutMode,
        QuoteValidator $quoteValidator,
        AgreementsValidator $agreementsValidator,
        CheckoutSession $checkoutSession,
        ?AbstractOrderData $orderData = null
    ) {
        parent::__construct($cartManagement, $orderData);

        $this->client = $client;
        $this->checkoutMode = $checkoutMode;
        $this->quoteValidator = $quoteValidator;
        $this->agreementsValidator = $agreementsValidator;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * The order is created once Dintero returns the customer to dintero/payment/success
     *
     * @return bool
     */
    public function canPlaceOrder(): bool
    {
        return false;
    }

    /**
     * Only the redirect flow leaves the storefront
     *
     * @return bool
     */
    public function canRedirect(): bool
    {
        return $this->checkoutMode->isRedirect();
    }

    /**
     * Own the redirect result so the customer is not told the order was completed already
     *
     * @param EvaluationResultFactory $resultFactory
     * @param int|null $orderId
     * @return EvaluationResultInterface
     * @throws LocalizedException
     * @throws \Magento\Payment\Gateway\Http\ClientException
     * @throws \Magento\Payment\Gateway\Http\ConverterException
     */
    public function evaluateCompletion(
        EvaluationResultFactory $resultFactory,
        int|null $orderId = null
    ): EvaluationResultInterface {
        if (!$this->canRedirect()) {
            return $resultFactory->createSuccess();
        }

        /** @var PlaceOrderRedirect $redirect */
        $redirect = $resultFactory->create(PlaceOrderRedirect::class, [
            'url' => $this->getRedirectUrl($this->checkoutSession->getQuote(), $orderId)
        ]);

        return $redirect->withRedirectTemplate('Dintero_HyvaCheckout::main/place-order/redirect.phtml');
    }

    /**
     * Validate the quote and initialize a Dintero session to redirect to
     *
     * @param Quote $quote
     * @param int|null $orderId
     * @return string
     * @throws LocalizedException
     * @throws \Magento\Payment\Gateway\Http\ClientException
     * @throws \Magento\Payment\Gateway\Http\ConverterException
     */
    public function getRedirectUrl(Quote $quote, ?int $orderId = null): string
    {
        if ($this->redirectUrl !== null) {
            return $this->redirectUrl;
        }

        $this->quoteValidator->validate($quote);

        if (!$this->agreementsValidator->validate($quote->getPayment())) {
            throw new LocalizedException(__('Please, accept the terms and conditions'));
        }

        $response = $this->client->initSessionFromQuote($quote);

        if (empty($response['url'])) {
            throw new LocalizedException(__('Could not initialize Dintero Checkout'));
        }

        return $this->redirectUrl = $response['url'];
    }
}
