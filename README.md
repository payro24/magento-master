== payro24 Gateway
Contributors: JMDMahdi, meysamrazmi, vispa
Stable tag: 1.0.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

== Description ==

After installing and enabling this plugin, your customers can pay through payro24 gateway.
For doing a transaction through payro24 gateway, you must have an API Key. You can obtain the API Key by going to your [dashboard](https://payro24.ir/dashboard/web-services) in your payro24 [account](https://payro24.ir/user).

== Installation/Usage

after copying the plugin code into app directory, run the following commands in magento_root directory

php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy -f
php bin/magento cache:flush

the you should be able to see payro24 payment method in:
Stores -> Configuration -> Sales -> Payment Methods -> Other Payment Methods -> payro24

== Change log

- 10/24/20  V 1.0.1 add Get and Post handler for new payro24 update

- 08/09/06  V 1.0.0 Initial revision
