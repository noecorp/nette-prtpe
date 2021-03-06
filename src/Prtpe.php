<?php

namespace Prtpe;

use Curl\Curl;


/**
 * Class Prtpe
 *
 * @author  geniv
 * @package Prtpe
 */
class Prtpe
{
    private $parameters = [];
    private $testMode = false;
    private $descriptor = null;
    private $createRegistration = false;
    private $registrations = [];
    /** hosts */
    const TEST = 'https://test.prtpe.com/';
    const LIVE = 'https://prtpe.com/';


    /**
     * Prtpe constructor.
     *
     * @param array $parameters
     */
    public function __construct(array $parameters)
    {
        $this->parameters = $parameters;
    }


    /**
     * Set test mode.
     *
     * TRUE = devel, FALSE = production
     *
     * @param bool $mode
     */
    public function setTest($mode = true)
    {
        $this->testMode = $mode;
    }


    /**
     * Get test mode.
     *
     * @return bool
     */
    public function isTestMode()
    {
        return $this->testMode;
    }


    /**
     * Internal init curl.
     *
     * @return Curl
     */
    private function initCurl()
    {
        $curl = new Curl;
        $curl->setOpt(CURLOPT_SSL_VERIFYPEER, !$this->testMode);
        return $curl;
    }


    /**
     * Get internal authentication.
     *
     * @return array
     */
    private function getAuthentication()
    {
        return [
            'authentication.userId'   => $this->parameters['userId'],
            'authentication.password' => $this->parameters['password'],
            'authentication.entityId' => $this->parameters['entityId'],
        ];
    }


    /**
     * Payment descriptor.
     *
     * @param $text
     * @return $this
     */
    public function setDescriptor($text)
    {
        if (strlen($text) >= 1 && strlen($text) <= 127) {
            $this->descriptor = $text;
        }
        return $this;
    }


    /**
     * Get internal payment descriptor.
     *
     * @return array
     */
    private function getDescriptor()
    {
        return ($this->descriptor ? ['descriptor' => $this->descriptor] : []);
    }


    /**
     * Set store payment.
     *
     * @param $state
     * @return $this
     */
    public function setStorePayment($state)
    {
        $this->createRegistration = $state;
        return $this;
    }


    /**
     * Get store payment.
     *
     * @return array
     */
    private function getStorePayment()
    {
        return ($this->createRegistration ? ['createRegistration' => true] : []);
    }


    /**
     * Add registration.
     *
     * @param $registrationId
     * @return $this
     */
    public function addRegistration($registrationId)
    {
        if ($registrationId) {
            $this->registrations[] = $registrationId;
        }
        return $this;
    }


    /**
     * Get registrations.
     *
     * @return array
     */
    private function getRegistrations()
    {
        $result = [];
        if ($this->registrations) {
            foreach ($this->registrations as $index => $item) {
                $result['registrations[' . $index . '].id'] = $item;
            }
        }
        return $result;
    }


    /**
     * COPY&PAY.
     *
     * https://docs.prtpe.com/tutorials/integration-guide
     */


    /**
     * Checkout.
     * COPY&PAY.
     *
     * @param        $amount
     * @param string $currency
     * @param string $paymentType
     * @return Response
     */
    public function checkout($amount, $currency = 'EUR', $paymentType = 'DB')
    {
        $curl = $this->initCurl();
        $url = ($this->testMode ? self::TEST : self::LIVE) . 'v1/checkouts';
        $curl->post($url, [
                'amount'      => $amount,
                'currency'    => $currency,
                'paymentType' => $paymentType,
            ] +
            $this->getDescriptor() +
            $this->getAuthentication() +
            $this->getStorePayment() +
            $this->getRegistrations()
        );
        return new Response($curl);
    }


    /**
     * Get payment widget script.
     *
     * @param $checkoutId
     * @return string
     */
    public function getPaymentWidgetsScript($checkoutId)
    {
        $url = ($this->testMode ? self::TEST : self::LIVE);
        return '<script src="' . $url . 'v1/paymentWidgets.js?checkoutId=' . $checkoutId . '"></script>';
    }


    /**
     * Get payment widgets form.
     *
     * https://docs.prtpe.com/tutorials/integration-guide/customisation
     * https://docs.prtpe.com/tutorials/integration-guide/advanced-options
     *
     * @param       $shopperResultUrl
     * @param array $brands
     * @return string
     */
    public function getPaymentWidgetsForm($shopperResultUrl, $brands = ['VISA', 'MASTER'])
    {
        $dataBrand = implode(' ', $brands);
        return '<form action="' . $shopperResultUrl . '" class="paymentWidgets" data-brands="' . $dataBrand . '"></form>';
    }


    /**
     * Get status checkout.
     *
     * @param $resourcePath
     * @return Response
     */
    public function getStatusCheckout($resourcePath)
    {
        $curl = $this->initCurl();
        $url = ($this->testMode ? self::TEST : self::LIVE) . $resourcePath;
        $curl->get($url, $this->getAuthentication());
        return new Response($curl);
    }

    /**
     * Server-to-Server.
     *
     * https://docs.prtpe.com/tutorials/server-to-server
     */


    /**
     * Payment.
     *
     * @param Card   $card
     * @param        $amount
     * @param string $paymentBrand
     * @param string $currency
     * @param string $paymentType
     * @return Response
     */
    public function payment(Card $card, $amount, $paymentBrand = 'VISA', $currency = 'EUR', $paymentType = 'DB')
    {
        $curl = $this->initCurl();
        $url = ($this->testMode ? self::TEST : self::LIVE) . 'v1/payments';
        $curl->post($url, [
                'amount'       => $amount,
                'currency'     => $currency,
                'paymentBrand' => $paymentBrand,
                'paymentType'  => $paymentType,
            ] +
            $card->toArray() +
            $this->getDescriptor() +
            $this->getAuthentication()
        );
        return new Response($curl);
    }


    /**
     * Get status payment.
     *
     * @param $checkoutId
     * @return Response
     */
    public function getStatusPayment($checkoutId)
    {
        $curl = $this->initCurl();
        $url = ($this->testMode ? self::TEST : self::LIVE) . 'v1/payments/' . $checkoutId;
        $curl->get($url, $this->getAuthentication());
        return new Response($curl);
    }


    /**
     * Store data for recurring payment.
     *
     * @param Card   $card
     * @param string $paymentBrand
     * @return Response
     */
    public function storePaymentData(Card $card, $paymentBrand = 'VISA')
    {
        $curl = $this->initCurl();
        $url = ($this->testMode ? self::TEST : self::LIVE) . 'v1/registrations';
        $curl->post($url, [
                'paymentBrand' => $paymentBrand,
            ] +
            $card->toArray() +
            $this->getAuthentication()
        );
        return new Response($curl);
    }


    /**
     * Recurring payment.
     *
     * COPY&PAY & Server-to-Server.
     */


    /**
     * Send recurring payment.
     *
     * @param        $registrationId
     * @param        $amount
     * @param string $paymentBrand
     * @param string $currency
     * @param string $paymentType
     * @return Response
     */
    public function sendRepeatedPayment($registrationId, $amount, $paymentBrand = 'VISA', $currency = 'EUR', $paymentType = 'DB')
    {
        $curl = $this->initCurl();
        $url = ($this->testMode ? self::TEST : self::LIVE) . 'v1/registrations/' . $registrationId . '/payments';
        $curl->post($url, [
                'amount'        => $amount,
                'currency'      => $currency,
                'paymentBrand'  => $paymentBrand,
                'paymentType'   => $paymentType,
                'recurringType' => 'REPEATED',
            ] +
            $this->getDescriptor() +
            $this->getAuthentication()
        );
        return new Response($curl);
    }


    /**
     * Delete recurring payment.
     *
     * @param $registrationId
     * @return Response
     */
    public function deleteStorePaymentData($registrationId)
    {
        $curl = $this->initCurl();
        $url = ($this->testMode ? self::TEST : self::LIVE) . 'v1/registrations/' . $registrationId;
        $curl->delete($url, $this->getAuthentication());
        return new Response($curl);
    }
}
