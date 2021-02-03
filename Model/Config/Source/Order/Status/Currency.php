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
namespace payro24\payro24\Model\Config\Source\Order\Status;

use Magento\Sales\Model\Config\Source\Order\Status;

class Currency extends Status
{
    protected $_stateStatuses = [
        "RIAL",
        "TOMAN"
    ];
}
