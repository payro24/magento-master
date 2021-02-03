<?php
/**
 * payro24 payment gateway
 *
 * @developer JMDMahdi, meysamrazmi, vispa
 * @publisher payro24
 * @copyright (C) 2020 payro24
 * @license http://www.gnu.org/licenses/gpl-2.0.html GPLv2 or later
 *
 * https://payro24.ir
 */
namespace payro24\payro24\Observer;

use Magento\Framework\Event\ObserverInterface;
use \Magento\Framework\UrlInterface;

class AfterPlaceOrder implements ObserverInterface
{
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $orderId = $observer->getEvent()->getOrder()->getId();

        $domain = ($_SERVER['HTTP_HOST'] != 'localhost') ? $_SERVER['HTTP_HOST'] : false;
        setcookie('payro24_order_id', $orderId, time()+3600, '/', $domain, false);
    }

}
