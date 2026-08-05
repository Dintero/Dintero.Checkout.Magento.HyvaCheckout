<?php

namespace Dintero\HyvaCheckout\Model\Magewire\Payment;

use Dintero\Checkout\Helper\Config as ConfigHelper;
use Dintero\Checkout\Model\Agreements\Validator as AgreementsValidator;
use Dintero\Checkout\Model\Api\Client;
use Dintero\HyvaCheckout\Model\CheckoutMode;
use Dintero\HyvaCheckout\Model\QuoteValidator;
use Hyva\Checkout\Model\Magewire\Component\Evaluation\PlaceOrderRedirect;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultFactory;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultInterface;
use Hyva\Checkout\Model\Magewire\Payment\AbstractOrderData;
use Hyva\Checkout\Model\Magewire\Payment\AbstractPlaceOrderService;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\OrderRepositoryInterface;

/**
 * Places the order and produces the Dintero hosted checkout url for the redirect flow.
 *
 * The order is placed before the customer leaves the storefront, exactly like the non Hyva
 * redirect flow in \Dintero\Checkout\Controller\Payment\Place. That keeps the merchant reference
 * tied to a committed order increment id, so a retried checkout can never hand out a second
 * payable Dintero session for an order number that is already in use.
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
     * @var ConfigHelper $configHelper
     */
    private $configHelper;

    /**
     * @var CartRepositoryInterface $quoteRepository
     */
    private $quoteRepository;

    /**
     * @var OrderRepositoryInterface $orderRepository
     */
    private $orderRepository;

    /**
     * @var Quote|null $placedQuote
     */
    private $placedQuote;

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
     * @param ConfigHelper $configHelper
     * @param CartRepositoryInterface $quoteRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param AbstractOrderData|null $orderData
     */
    public function __construct(
        CartManagementInterface $cartManagement,
        Client $client,
        CheckoutMode $checkoutMode,
        QuoteValidator $quoteValidator,
        AgreementsValidator $agreementsValidator,
        ConfigHelper $configHelper,
        CartRepositoryInterface $quoteRepository,
        OrderRepositoryInterface $orderRepository,
        ?AbstractOrderData $orderData = null
    ) {
        parent::__construct($cartManagement, $orderData);

        $this->client = $client;
        $this->checkoutMode = $checkoutMode;
        $this->quoteValidator = $quoteValidator;
        $this->agreementsValidator = $agreementsValidator;
        $this->configHelper = $configHelper;
        $this->quoteRepository = $quoteRepository;
        $this->orderRepository = $orderRepository;
    }

    /**
     * The embedded flow places the order from the callback, the redirect flow places it up front
     *
     * @return bool
     */
    public function canPlaceOrder(): bool
    {
        return $this->checkoutMode->isRedirect();
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
     * Validate the quote and place the order before the customer is sent to Dintero
     *
     * @param Quote $quote
     * @return int
     * @throws LocalizedException
     */
    public function placeOrder(Quote $quote): int
    {
        $this->quoteValidator->validate($quote);

        if (!$this->agreementsValidator->validate($quote->getPayment())) {
            throw new LocalizedException(__('Please, accept the terms and conditions'));
        }

        /*
         * The line id generator is resolved from the quote when the order items are built for the
         * api request, so the code has to be persisted before the quote is converted into an order.
         */
        $quote->setDinteroGeneratorCode($this->configHelper->getLineIdFieldName());
        $this->quoteRepository->save($quote);

        $this->placedQuote = $quote;

        return parent::placeOrder($quote);
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
            /*
             * The embedded flow never places the order through this service: the Dintero iframe posts
             * to dintero/payment/success instead. Reporting success here would let the main component
             * finish the checkout without an order having been created.
             */
            return $resultFactory->createErrorMessage(
                __('The Dintero embedded checkout completes the payment, the order cannot be placed here.')
            );
        }

        if (!$this->placedQuote) {
            throw new LocalizedException(__('Could not initialize Dintero Checkout'));
        }

        /** @var PlaceOrderRedirect $redirect */
        $redirect = $resultFactory->create(PlaceOrderRedirect::class, [
            'url' => $this->getRedirectUrl($this->placedQuote, $orderId)
        ]);

        return $redirect->withRedirectTemplate('Dintero_HyvaCheckout::main/place-order/redirect.phtml');
    }

    /**
     * Initialize a Dintero session for the placed order and return the hosted checkout url
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

        if (!$orderId) {
            throw new LocalizedException(__('Could not initialize Dintero Checkout'));
        }

        $order = $this->orderRepository->get($orderId);
        $data = $this->client->initCheckout($order);

        if (($data['error']['code'] ?? null) === 'INVALID_REQUEST_PARAMETER') {
            throw new LocalizedException(__($this->processErrors($data['error']['errors'] ?? [])));
        }

        if (empty($data['url'])) {
            throw new LocalizedException(__('Could not initialize Dintero Checkout'));
        }

        $order->getPayment()->setAdditionalInformation('session_id', $data['id'] ?? null);
        $this->orderRepository->save($order);

        return $this->redirectUrl = $this->configHelper->resolveCheckoutUrl($data['url']);
    }

    /**
     * Flatten the api validation errors into a single message
     *
     * @param array $errors
     * @return string
     */
    private function processErrors($errors)
    {
        $errors = array_map(function ($error) {
            return $error['description'] ?? null;
        }, $errors);

        return implode(PHP_EOL, array_unique($errors));
    }
}
