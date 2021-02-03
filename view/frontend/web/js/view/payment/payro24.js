define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (Component, rendererList) {
        'use strict';
        rendererList.push(
            {
                type: 'payro24',
                component: 'payro24_payro24/js/view/payment/method-renderer/payro24-method'
            }
        );
        return Component.extend({
            defaults: {
                redirectAfterPlaceOrder: true
            },
            afterPlaceOrder: function (data, event) {
                window.location.replace('payro24/redirect/index');
            }
        });
    }
);
