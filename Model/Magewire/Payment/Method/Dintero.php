<?php

namespace Dintero\HyvaCheckout\Model\Magewire\Payment\Method;

use Dintero\Checkout\Api\SessionManagementInterface as DinteroSessionManagement;
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
     * @var Session $checkoutSession
     */
    private $checkoutSession;

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
     * @param Session $checkoutSession
     * @param AgreementsValidatorInterface $agreementValidator
     * @param CartRepositoryInterface $cartRepository
     * @param SubmitQuoteValidator $submitQuoteValidator
     * @param QuoteAddressValidator $quoteAddressValidator
     * @param DinteroSessionManagement $dinteroSessionManagement
     * @param EvaluationResultFactory $evaluationResultFactory
     */
    public function __construct(
        Session $checkoutSession,
        AgreementsValidatorInterface $agreementValidator,
        CartRepositoryInterface $cartRepository,
        SubmitQuoteValidator $submitQuoteValidator,
        QuoteAddressValidator $quoteAddressValidator,
        DinteroSessionManagement $dinteroSessionManagement,
        EvaluationResultFactory $evaluationResultFactory
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->agreementValidator = $agreementValidator;
        $this->cartRepository = $cartRepository;
        $this->submitQuoteValidator = $submitQuoteValidator;
        $this->quoteAddressValidator = $quoteAddressValidator;
        $this->dinteroSessionManagement = $dinteroSessionManagement;
        $this->evaluationResultFactory = $evaluationResultFactory;
    }

    /**
     * Report whether the payment step is complete
     *
     * The redirect towards Dintero is never produced here: it originates from the place order
     * service, which only hands out a url once the whole checkout validates.
     *
     * @param EvaluationResultFactory $resultFactory
     * @return EvaluationResultInterface
     * @see \Dintero\HyvaCheckout\Model\Magewire\Payment\PlaceOrderService
     */
    public function evaluateCompletion(EvaluationResultFactory $resultFactory): EvaluationResultInterface
    {
        return $this->validationResult ?? $resultFactory->createSuccess();
    }

    /**
     * Check if all terms and conditions are checked if enabled
     *
     * @param array $agreementIds Hyva hands out a map of agreement id => accepted
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

        $acceptedIds = $this->getAcceptedAgreementIds($agreementIds);

        if (!$this->agreementValidator->isValid($acceptedIds)) {
            throw new LocalizedException(__('Please, accept the terms and conditions'));
        }

        $quote->getPayment()->setAdditionalInformation('agreement_ids', $acceptedIds);
        $this->cartRepository->save($quote);

        return true;
    }

    /**
     * Reduce the acceptance map to the list of ids the customer agreed to
     *
     * @param array $agreementIds
     * @return array
     * @see \Hyva\Checkout\Magewire\Checkout\TermsConditions::$termAcceptance
     */
    private function getAcceptedAgreementIds(array $agreementIds)
    {
        return array_keys(array_filter($agreementIds));
    }

    /**
     * Validate checkout
     *
     * @param string $sessionId
     * @param array $agreementIds Hyva hands out a map of agreement id => accepted
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
                $quotePayment->getExtensionAttributes()->setAgreementIds(
                    $this->getAcceptedAgreementIds($agreementIds)
                );
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
