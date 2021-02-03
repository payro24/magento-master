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
namespace payro24\payro24\Block;

use Magento\Backend\Model\View\Result\RedirectFactory;
use Magento\Customer\Model\Session;
use Magento\Framework\View\Element\Template;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Transaction;

class payro24 extends \Magento\Framework\View\Element\Template
{
    protected $_checkoutSession;
    protected $_orderFactory;
    protected $_scopeConfig;
    protected $_urlBuilder;
    protected $messageManager;
    protected $redirectFactory;
    protected $catalogSession;
    protected $customer_session;
    protected $order;
    protected $response;
    protected $session;
    protected $transactionBuilder;

    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        Session $customer_session,
        RedirectFactory $redirectFactory,
        \Magento\Framework\App\Response\Http $response,
        \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder,
        Template\Context $context,
        array $data
    ) {
        $this->customer_session = $customer_session;
        $this->_checkoutSession = $checkoutSession;
        $this->_orderFactory = $orderFactory;
        $this->_scopeConfig = $context->getScopeConfig();
        $this->_urlBuilder = $context->getUrlBuilder();
        $this->messageManager = $messageManager;
        $this->redirectFactory = $redirectFactory;
        $this->response = $response;
        $this->transactionBuilder = $transactionBuilder;
        parent::__construct($context, $data);
    }

    private function getOrder()
    {
        if (! $this->order) {
            $this->order = $this->_orderFactory->create()->load($this->getOrderId());
        }
        return $this->order;
    }

    public function changeStatus($status, $msg = null)
    {
        $order = $this->getOrder();
        if (empty($msg)) {
            $order->setStatus($status);
        } else {
            $order->addStatusToHistory($status, $msg, true);
        }
        $order->save();
    }

    public function getOrderId()
    {
        return isset($_COOKIE['payro24_order_id']) ? $_COOKIE['payro24_order_id'] : false;
    }

    private function getConfig($value)
    {
        return $this->_scopeConfig->getValue('payment/payro24/' . $value, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    public function getAfterOrderStatus()
    {
        return $this->getConfig('after_order_status');
    }

    public function getOrderStatus()
    {
        return $this->getConfig('order_status');
    }

    protected function payro24_get_failed_message($track_id, $order_id)
    {
        return str_replace(["{track_id}", "{order_id}"], [$track_id, $order_id], $this->getConfig('failed_massage'));
    }

    protected function payro24_get_success_message($track_id, $order_id)
    {
        return str_replace(["{track_id}", "{order_id}"], [$track_id, $order_id], $this->getConfig('success_massage'));
    }

    public function redirect()
    {
        if (!$this->getOrderId()) {
            $this->response->setRedirect($this->_urlBuilder->getUrl(''));
            return "";
        }
        $response['state'] = false;
        $response['result'] = "";

        $api_key = $this->getConfig('api_key');
        $sandbox = $this->getConfig('sandbox') == 1 ? 'true' : 'false';
        $amount = intval($this->getOrder()->getGrandTotal());

        if (!empty($this->getConfig('currency')) && $this->getConfig('currency') == 1) {
            $amount *= 10;
        }

        $desc = "پرداخت سفارش شماره " . intval($this->getOrderId());
        $callback = $this->_urlBuilder->getUrl('payro24/redirect/callback');

        if (empty($amount)) {
            $response['result'] = 'واحد پول انتخاب شده پشتیبانی نمی شود.';

            $this->changeStatus(Order::STATE_CLOSED, $response['result']);
            $this->messageManager->addErrorMessage($response['result']);

            $this->response->setRedirect($this->_urlBuilder->getUrl('checkout/onepage/failure'));
        }

        $billing  = $this->getOrder()->getBillingAddress();
        if ($billing->getEmail()) {
            $email = $billing->getEmail();
        } else {
            $email = $this->getOrder()->getCustomerEmail();
        }

        $data = [
            'order_id' => $this->getOrderId(),
            'amount' => $amount,
            'name' => $billing->getFirstname() . ' ' . $billing->getLastname(),
            'phone' => $billing->getTelephone(),
            'mail' => $email,
            'desc' => $desc,
            'callback' => $callback,
        ];
        $ch = curl_init('https://api.payro24.ir/v1.1/payment');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'P-TOKEN:' . $api_key,
            'P-SANDBOX:' . $sandbox,
        ]);

        $result = curl_exec($ch);
        $result = json_decode($result);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_status != 201 || empty($result) || empty($result->id) || empty($result->link)) {
            $response['result'] = sprintf('خطا: %s. کد خطا: %s', $result->error_message, $result->error_code);

            $this->changeStatus(Order::STATE_CLOSED, $response['result']);
            $this->messageManager->addErrorMessage($response['result']);

            $this->response->setRedirect($this->_urlBuilder->getUrl('checkout/onepage/failure'));
        } else {
            $this->_checkoutSession->setTestData($result->id);
            $this->changeStatus($this->getOrderStatus());
            $response['state'] = true;
            $this->response->setRedirect($result->link);
        }

        return $response;
    }

    public function callback()
    {
        $data = $this->getRequest()->getParams();
        $order = $this->getOrder();
        $response['state'] = false;
        $response['result'] = "";

        if (!$order->getData() || empty($data['id']) || empty($data['order_id'])) {
            $response['result'] = "تراکنش موجود نیست یا قبلا اعتبار سنجی شده است.";
        } else {
            $amount = intval($order->getGrandTotal());

            if (!empty($this->getConfig('currency')) && $this->getConfig('currency') == 1) {
                $amount *= 10;
            }

            $orderid = $this->getOrderId();
            $pid = $data['id'];
            $porder_id = $data['order_id'];

            if ($data['status'] != 10) {
                $response['result'] = sprintf('خطا: %s (کد: %s)', $this->getStatus($data['status']), $data['status']);
                $this->changeStatus(Order::STATE_CANCELED, $response['result']);

                $this->messageManager->addErrorMessage($response['result']);
                $this->response->setRedirect($this->_urlBuilder->getUrl('checkout/onepage/failure'));
            } elseif (empty($pid) || empty($porder_id) || $porder_id != $orderid) {
                $response['result'] = 'پارامتر های ورودی اشتباه هستند.';
                $this->changeStatus(Order::STATE_CANCELED, $response['result']);

                $this->messageManager->addErrorMessage($response['result']);
                $this->response->setRedirect($this->_urlBuilder->getUrl('checkout/onepage/failure'));
            } else {
                $api_key = $this->getConfig('api_key');
                $sandbox = $this->getConfig('sandbox') == 1 ? 'true' : 'false';

                $data = [
                    'id' => $pid,
                    'order_id' => $orderid,
                ];

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, 'https://api.payro24.ir/v1.1/payment/verify');
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'P-TOKEN:' . $api_key,
                    'P-SANDBOX:' . $sandbox,
                ]);

                $result = curl_exec($ch);
                $result = json_decode($result);
                $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($http_status != 200) {
                    $response['result'] = sprintf('خطا هنگام بررسی وضعیت تراکنش. کد خطا: %s, پیام خطا: %s', $result->error_code, $result->error_message);
                    $this->changeStatus(Order::STATE_CANCELED, $response['result']);

                    $this->messageManager->addErrorMessage($response['result']);
                    $this->response->setRedirect($this->_urlBuilder->getUrl('checkout/onepage/failure'));
                }

                $verify_status = empty($result->status) ? null : $result->status;
                $verify_track_id = empty($result->track_id) ? null : $result->track_id;
                $verify_order_id = empty($result->order_id) ? null : $result->order_id;
                $verify_amount = empty($result->amount) ? null : $result->amount;

                if (empty($verify_status) || empty($verify_track_id) || empty($verify_amount) || $verify_amount != $amount || $verify_status != 100 || $verify_order_id !== $orderid) {
                    $response['result'] = $this->payro24_get_failed_message($verify_track_id, $verify_order_id);
                    $this->changeStatus(Order::STATE_CANCELED, $response['result']);

                    $this->messageManager->addErrorMessage($response['result']);
                    $this->response->setRedirect($this->_urlBuilder->getUrl('checkout/onepage/failure'));
                } else {
                    $response['state'] = true;
                    $response['result'] = $this->payro24_get_success_message($verify_track_id, $verify_order_id);

                    $this->addTransaction($this->order, $verify_track_id, (array)$result->payment);

                    $this->order->addStatusToHistory($this->getAfterOrderStatus(), sprintf('<pre>%s</pre>', print_r($result->payment, true)), false);
                    $this->order->save();

                    $this->changeStatus($this->getAfterOrderStatus(), $response['result']);

                    $this->messageManager->addSuccessMessage($response['result']);
                    $this->response->setRedirect($this->_urlBuilder->getUrl('checkout/onepage/success', ['_secure' => true ]));
                }
            }
        }

        setcookie("payro24_order_id", "", time() - 3600, "/");

        return $response;
    }

    public function addTransaction($order, $txnId, $paymentData = [])
    {
        $payment = $order->getPayment();
        $payment->setMethod('payro24');
        $payment->setLastTransId($txnId);
        $payment->setTransactionId($txnId);
        $payment->setIsTransactionClosed(0);
        $payment->setAdditionalInformation([Transaction::RAW_DETAILS => (array) $paymentData]);
        $payment->setParentTransactionId(null);

        // Prepare transaction
        $transaction = $this->transactionBuilder->setPayment($payment)
            ->setOrder($order)
            ->setFailSafe(true)
            ->setTransactionId($txnId)
            ->setAdditionalInformation([Transaction::RAW_DETAILS => (array) $paymentData])
            ->build(Transaction::TYPE_CAPTURE);

        // Add transaction to payment
        $payment->addTransactionCommentsToOrder($transaction, __('The authorized TransactionId is %1.', $txnId));
        $payment->setParentTransactionId(null);

        // Save payment, transaction and order
        $payment->save();
        $order->save();
        $transaction->save()->close();

        return  $transaction->getTransactionId();
    }

    public function getStatus($messageNumber)
    {
        switch ($messageNumber) {
            case 1:
                return 'پرداخت انجام نشده است';
                break;
            case 2:
                return 'پرداخت ناموفق بوده است';
                break;
            case 3:
                return 'خطا رخ داده است';
                break;
            case 4:
                return 'بلوکه شده';
                break;
            case 5:
                return 'برگشت به پرداخت کننده';
                break;
            case 6:
                return 'برگشت خورده سیستمی';
                break;
            case 7:
                return 'انصراف از پرداخت';
                break;
            case 8:
                return 'به درگاه پرداخت منتقل شد';
                break;
            case 10:
                return 'در انتظار تایید پرداخت';
                break;
            case 100:
                return 'پرداخت تایید شده است';
                break;
            case 101:
                return 'پرداخت قبلا تایید شده است';
                break;
            case 200:
                return 'به دریافت کننده واریز شد';
                break;
        }
    }
}
