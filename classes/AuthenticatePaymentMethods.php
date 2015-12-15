<?php
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of AuthenticatePaymentMethods
 *
 * @author Stef
 */
class AuthenticatePaymentMethods
{

    static public function getCountryDependency($iso_code)
    {
        $localizations = array(
            'AU' => array('AU'),
            'BE' => array('BE'),
            'CN' => array('CN', 'MO'),
            'CZ' => array('CZ'),
            'DE' => array('DE'),
            'ES' => array('ES'),
            'FR' => array('FR'),
            'HK' => array('HK'),
            'IL' => array('IL'),
            'IT' => array('IT', 'VA'),
            'JP' => array('JP'),
            'MY' => array('MY'),
            'NL' => array('AN', 'NL'),
            'NZ' => array('NZ'),
            'PL' => array('PL'),
            'PT' => array('PT', 'BR'),
            'RA' => array('BN', 'ID', 'KH', 'LA', 'MX', 'PH', 'PW', 'TL', 'VN'),
            'RE' => array('AT', 'CH', 'DK', 'FI', 'GR', 'HU', 'LU', 'NO', 'RO', 'RU', 'SE', 'SK', 'SL', 'SN', 'UA'),
            'SG' => array('SG'),
            'TH' => array('TH'),
            'TR' => array('TR'),
            'TW' => array('TW')
        );

        foreach ($localizations as $key => $value)
            if (in_array($iso_code, $value)) return $key;

        return false;
    }

    static public function getPaymentMethodsByIsoCode($iso_code)
    {

        // WPS -> Web Payment Standard
        // HSS -> Web Payment Pro / Integral Evolution
        // ECS -> Express Checkout Solution
        // PPP -> PAYPAL PLUS

        $payment_method = array(
            'AU' => array(WPS, HSS, ECS),
            'BE' => array(WPS, ECS),
            'CN' => array(WPS, ECS),
            'CZ' => array(),
            'DE' => array(WPS, ECS, PPP),
            'ES' => array(WPS, HSS, ECS),
            'FR' => array(WPS, HSS, ECS),
            'HK' => array(WPS, HSS, ECS),
            'IL' => array(WPS, ECS),
            'IT' => array(WPS, HSS, ECS),
            'JP' => array(WPS, HSS, ECS),
            'MY' => array(WPS, ECS),
            'NL' => array(WPS, ECS),
            'NZ' => array(WPS, ECS),
            'PL' => array(WPS, ECS),
            'PT' => array(WPS, ECS),
            'RA' => array(WPS, ECS),
            'RE' => array(WPS, ECS),
            'SG' => array(WPS, ECS),
            'TH' => array(WPS, ECS),
            'TR' => array(WPS, ECS),
            'TW' => array(WPS, ECS)
        );

        return isset($payment_method[$iso_code]) ? $payment_method[$iso_code] : false;
    }

    static public function AuthenticatePaymentMethodByLang($iso_code)
    {
        return self::getPaymentMethodsByIsoCode(self::getCountryDependency($iso_code));
    }

    static function AuthenticatePaymentMethodByCountry($iso_code)
    {
        return self::getPaymentMethodsByIsoCode(self::getCountryDependency($iso_code));
    }
}