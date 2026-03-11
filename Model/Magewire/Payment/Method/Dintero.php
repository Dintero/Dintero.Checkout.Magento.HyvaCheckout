<?php

namespace Dintero\HyvaCheckout\Model\Magewire\Payment\Method;

use Dintero\Checkout\Helper\Config as ConfigHelper;
use Dintero\Checkout\Api\SessionManagementInterface as DinteroSessionManagement;
use Dintero\Checkout\Model\Api\Client;
use Hyva\Checkout\Model\Magewire\Component\EvaluationInterface;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultFactory;
use Hyva\Checkout\Model\Magewire\Component\EvaluationResultInterface;
use Magento\Checkout\Api\AgreementsValidatorInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote\Address\Validator as QuoteAddressValidator;
use Magento\Quote\Model\SubmitQuoteValidator;
use Magewirephp\Magewire\Component;

class Dintero extends Component implements EvaluationInterface
{
    /**
     * @var Client $client
     */
    private $client;

    /**
     * @var Session $checkoutSession
     */
    private $checkoutSession;

    /**
     * @var ConfigHelper $config
     */
    private $config;

    /**
     * @var AgreementsValidatorInterface $agreementValidator
     */
    private $agreementValidator;

    /**
     * @var CartRepositoryInterface $cartRepository
     */
    private $cartRepository;

    /**
     * @var SubmitQuoteValidator $submitQuoteValidator
     */
    private $submitQuoteValidator;

    /**
     * @var QuoteAddressValidator $quoteAddressValidator
     */
    private $quoteAddressValidator;

    /**
     * @var DinteroSessionManagement $dinteroSessionManagement
     */
    private $dinteroSessionManagement;

    private $evaluationResultFactory;

    private $validationResult;

    /**
     * Define class dependencies
     *
     * @param Client $client
     * @param Session $checkoutSession
     * @param ConfigHelper $config
     * @param AgreementsValidatorInterface $agreementValidator
     * @param CartRepositoryInterface $cartRepository
     * @param SubmitQuoteValidator $submitQuoteValidator
     * @param QuoteAddressValidator $quoteAddressValidator
     * @param DinteroSessionManagement $dinteroSessionManagement
     * @param EvaluationResultFactory $evaluationResultFactory
     */
    public function __construct(
        Client $client,
        Session $checkoutSession,
        ConfigHelper $config,
        AgreementsValidatorInterface $agreementValidator,
        CartRepositoryInterface $cartRepository,
        SubmitQuoteValidator $submitQuoteValidator,
        QuoteAddressValidator $quoteAddressValidator,
        DinteroSessionManagement $dinteroSessionManagement,
        EvaluationResultFactory $evaluationResultFactory
    ) {
        $this->client = $client;
        $this->checkoutSession = $checkoutSession;
        $this->config = $config;
        $this->agreementValidator = $agreementValidator;
        $this->cartRepository = $cartRepository;
        $this->submitQuoteValidator = $submitQuoteValidator;
        $this->quoteAddressValidator = $quoteAddressValidator;
        $this->dinteroSessionManagement = $dinteroSessionManagement;
        $this->evaluationResultFactory = $evaluationResultFactory;
    }

    /**
     * Initialize Dintero checkout
     *
     * @param EvaluationResultFactory $resultFactory
     * @return \Hyva\Checkout\Model\Magewire\Component\Evaluation\Redirect|EvaluationResultInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Payment\Gateway\Http\ClientException
     * @throws \Magento\Payment\Gateway\Http\ConverterException
     */
    public function evaluateCompletion(EvaluationResultFactory $resultFactory): EvaluationResultInterface
    {
        if ($this->config->isEmbedded() || $this->config->isEmbeddedExpress()) {

            if ($this->validationResult !== null) {
                return $this->validationResult;
            }

            return $resultFactory->createSuccess();
        }

        $response = $this->client->initSessionFromQuote($this->checkoutSession->getQuote());
        if (!$response['url']) {
            return $resultFactory->createErrorMessage(__('Could not initialize Dintero Checkout'));
        }

        return $resultFactory->createRedirect($response['url']);
    }

    /**
     * Check if all terms and conditions are checked if enabled
     *
     * @return bool
     * @throws LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function checkAndSaveAgreements(array $agreementIds = [])
    {

        $quote = $this->checkoutSession->getQuote();
        if (!$quote) {
            throw new LocalizedException(__('Quote not found'));
        }

        if (!$this->agreementValidator->isValid($agreementIds)) {
            throw new LocalizedException(__('Please, accept the terms and conditions'));
        }

        $quote->getPayment()->setAdditionalInformation('agreement_ids', array_keys(array_filter($agreementIds)));
        $this->cartRepository->save($quote);

        return true;
    }

    /**
     * Validate checkout
     *
     * @param string $sessionId
     * @return bool
     * @throws LocalizedException
     */
    public function validateCheckout($sessionId, $agreementIds)
    {

        try {

            $quote = $this->checkoutSession->getQuote();

            $this->submitQuoteValidator->validateQuote($quote);

            if ($quote->getPayment()->getMethod() !== \Dintero\Checkout\Model\Dintero::METHOD_CODE) {
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

            $quotePayment = $quote->getPayment();
            if ($quotePayment->getExtensionAttributes() !== null) {
                $quotePayment->getExtensionAttributes()->setAgreementIds(array_keys(array_filter($agreementIds)));
            }

            if (!$this->dinteroSessionManagement->validateSession($sessionId)) {
                throw new LocalizedException(__('Dintero session has expired or is not valid.'));
            }
            $this->validationResult = $this->evaluationResultFactory->createSuccess();
            return true;
        } catch (LocalizedException $e) {
            $this->validationResult = $this->evaluationResultFactory->createErrorMessage($e->getMessage());
            return false;
        }
    }
}
