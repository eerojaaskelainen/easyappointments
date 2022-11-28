<?php defined('BASEPATH') or exit('No direct script access allowed');

/* ----------------------------------------------------------------------------
 * Easy!Appointments - Open Source Web Scheduler
 *
 * @package     EasyAppointments
 * @author      Eero Jääskeläinen <eero.jaaskelainen@gmail.com>
 * @copyright   Copyright (c) 2013 - 2020, Alex Tselegidis
 * @license     http://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        http://easyappointments.org
 * @since       v1.5.0
 * ---------------------------------------------------------------------------- */

 class MobilePay
 {
    /**
     * @var EA_Controller
     */
    protected $CI;

    public function __construct()
    {
        $this->CI =& get_instance();    
        $this->CI->load->model('settings_model');
        $this->CI->load->model('appointments_model');
        $this->CI->load->model('services_model');
        $this->CI->load->model('providers_model');
    }

    protected function getSetting(string $name)
    {
        $set = $this->CI->settings_model->get(['name' => $name]);
        if (empty($set))
        {
            return NULL;
        }
        return $set[0]['value'];
    }

    protected function getNumber()
    {
        return $this->getSetting('company_mobilepay');
    }
    protected function isEnabled()
    {
        return !empty($this->getNumber());
    }
    protected function getPrice(int $serviceId, bool $withCurrency = FALSE)
    {
        $ret = $this->CI->services_model->value($serviceId,'price');
        if ($withCurrency)
        {
            $cur = $this->CI->services_model->value($serviceId,'currency');
            $ret = !empty($cur) ? sprintf('%s %s',$ret,$cur) : $ret;
        }
        return $ret;
    }
    

    public function renderPaymentLink(array &$appointment)
    {
        if (! $this->isEnabled())
        {
            return '';
        }
        $serviceId = $appointment['id_services'];
        $providerId = $appointment['id_users_provider'];

        if (empty($serviceId))
        {
            throw new InvalidArgumentException('Could not find service ID from appointment');
        }

        $number = $this->getNumber();
        $amount = $this->getPrice($serviceId);

        if (empty($amount))
        {
            return preg_replace_callback('/\{([^\}]+)\}/', function($ma) use ($number) {
                switch ($ma[1])
                {
                    case 'mobilepay_number':
                        return $number;
                    default:
                        return '';

                }
            },
            lang('company_mobilepay_payment_general'));
        }



        $comment = lang('company_mobilepay_comment');
        $comment = preg_replace_callback('/\{([^\}]+)\}/',function($ma) use ($serviceId, $providerId,$appointment) {
            switch ($ma[1])
            {
                case 'service':
                    return $this->CI->services_model->value($serviceId,'name');
                case 'provider':
                    return $this->CI->providers_model->value($providerId,'name');
                case 'hash':
                    return '#'.$appointment['hash'];
                case 'from':
                case 'to':
                default:
                    return ' ';
            }
        },
        $comment);
        $comment = rawurlencode($comment);

        $lock = 0;

        /* This Schema version does not work, Gmail filters it away:
        $link = sprintf('mobilepay://send?amount=%s&phone=%s&comment=%s&lock=%d',
            $amount,
            $number,
            $comment,
            $lock
        );*/
        $link = sprintf('https://www.mobilepay.fi/Yrityksille/Maksulinkki/maksulinkki-vastaus?phone=%s&amount=%s&comment=%s&lock=%d',
            $number,
            $amount,
            $comment,
            $lock
        );
        


        $linkText = lang('company_mobilepay_paymentlink');
        $linkText = preg_replace_callback('/\{([^\}]+)\}/',function($ma) use($link, $amount, $serviceId,$number) {
            switch ($ma[1])
            {
                case 'link':
                    return $link;
                case 'price':
                    return !empty($amount) ? $this->getPrice($serviceId,TRUE) : '';
                case 'mobilepay_number':
                    return $number;
            }
        },
        $linkText);

        return $linkText;
    }
 }