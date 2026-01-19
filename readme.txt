=== Bonus for Woo ===
Contributors: calliko
Donate link: https://yoomoney.ru/to/410011302808683
Tags: loyalty, cashback, points, reward, referral
Requires at least:  5.0
Tested up to:  6.9
WC requires at least: 6.0
WC tested up to: 10.4.3
Stable tag: 7.6.4
Requires PHP: 7.4
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

This plugin is designed to create a bonus system with cashback.

== Description ==

This plugin is designed to create a bonus system with cashback.
The cashback percentage is calculated based on the users' status in the form of bonus points.
Each user status has a corresponding cashback percentage.
The users' status depends on the total amount of the users orders.
Cashback is accumulated in the client's virtual wallet.

[youtube https://youtu.be/6u4mHdHVhkE]

== Free plugin features ==
* Points for product reviews.
* Integer and decimal points
* Hide the ability to spend points for discounted items.
* Show the history of bonus points.
* Email notifications.
* Export and import points.
* Shortcodes.

== Additional settings for the PRO version ==
* Points on your birthday.
* Daily points for the first login.
* Points for registration.
* Exclude categories of products that cannot be purchased with cashback points.
* Cashback is not accrued for discounted items.
* Exclude payment methods.
* Exclude items that cannot be purchased with Cashback Points.
* Minimum order amount to redeem points.
* Withdrawal of bonus points for inactivity.
* Referral system.
* Coupons.
* Points accrual delay.
* Accrual of points for previous orders.

== Addons for Bonus for Woo plugin ==

**Bonus for Woo addon API**
API for Bonus for Woo for CRM systems. [more details](https://computy.ru/blog/docs/bonus-for-woo/platnye-dopolneniya/bonus-for-woo-addon-api/)

**BFW addon for referral**
Expands the Bonus for Woo referral system. [more details](https://computy.ru/blog/docs/bonus-for-woo/platnye-dopolneniya/bfw-addon-for-referral/)

**BFW addon write off percentage**
The percentage of points written off depending on the clients status. [more details](https://computy.ru/blog/docs/bonus-for-woo/platnye-dopolneniya/bfw-addon-write-off-percentage/)

**BFW addon transfer points for users**
Transfers of bonus points between users. [more details](https://computy.ru/blog/docs/bonus-for-woo/platnye-dopolneniya/bfw-addon-transfer-points-for-users/)

== Testing ==
You can test the plugin on [**this page**](https://demo.tastewp.com/bonus-for-woo)

== Compatibility ==
* With the WPML plugin
* With the Elementor plugin
* With the Yandex.Delivery (Boxberry) plugin for WooCommerce
* With the Post SMTP plugin
* With high-performance order storage
* Multi-site support


== SUPPORT ==
If you need support or have questions, please write to our [**support**](https://wordpress.org/support/plugin/bonus-for-woo/) or [**blog**](https://computy.ru/blog/bonus-for-woo-wordpress/#reply-title).

== Installation ==

1. Upload dir `bonus-for-woo-computy` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress


== Screenshots ==

1. Options in adminpanel
2. View plugin on userpage
3. View the number of points received on the product page.
4. Calculation by points in the basket and checkout.
5. History of accrual of points in the client's account
6. User edit page.
7. E-mail notification settings page.
8. Coupon management page.
9. Statistics page

== Frequently Asked Questions ==

 
= When are points awarded? =
When the administrator marks the order as "Completed"

= Why are points not awarded immediately after the order is paid? =
This is done on purpose to exclude the case when the client has spent the earned
points, but decided to return the last product. In this case, the bonus balance goes negative.

= When are points deducted? =
When the customer confirms the order in checkout.

= Possible cashback is not displayed in the shopping cart and checkout. =
You are most likely logged in with an administrator account. Administrators are not involved
in the loyalty system.

= Does the WooCommerce plugin support High-Performance Order Storage? =
Yes, the plugin supports WooCommerce High-Performance Order Storage.




== Changelog ==

= 7.6.4 - 2026-01-19 =
* Transformed the frontend from admin-ajax to REST API

= 7.6.3 - 2025-12-31 =
* Code optimization

= 7.6.2 - 2025-12-16 =
* Bug fixes.

= 7.6.1 - 2025-12-16 =
* Added a correct error message when a bonus points coupon code is entered into the WooCommerce coupon form.
* Added the shortcode [bfw_coupon_form]

= 7.6.0 - 2025-12-08 =
* Added the option to delay point accrual.
* Added display of the planned cashback accrual in the order editor.
* Added display of the planned cashback accrual time in the order editor.
* Added a tool for accruing points for previous orders.
* Fixed the sharing of bonus points with other coupons.
* Code optimization

= 7.5.2 - 2025-12-05 =
* Compatibility with WordPress 6.9
* Changed notifications when adding and removing points.
* Fixed the sharing of bonus points with other coupons.
* Bug fixes.

= 7.5.1 - 2025-11-25 =
* Fixed duplicate points.

= 7.5.0 - 2025-11-25 =
* Added secure coupon application.
* Added form state saving in the body data attribute.
* Fixed cashback calculation for third-party coupons.
* Cashback is now calculated based on the price at the time of purchase.

= 7.4.6 - 2025-11-14 =
* Fixed an infinite recursion error when writing off points.

= 7.4.5 - 2025-10-20 =
* High-performance order storage support.

= 7.4.4 - 2025-10-19 =
* Fixed the class registry. (Thanks to @avsalexandra)
* Fixed an error with accrual of points for referral orders. (Thanks to @avsalexandra)
* Fixed a critical error when deleting an inviter user. (Thanks to @avsalexandra)

= 7.4.3 - 2025-10-04 =
* Fixed division by zero error.

= 7.4.2 - 2025-09-09 =
* Fixed a bug in the product card.

= 7.4.1 - 2025-09-01 =
* Added the ability to see the order number from referral accruals in the history

= 7.4.0 - 2025-09-01 =
* Added a status slug to the card on the bonus system page.
* Fixed the display of individual product cashback in the product card.
* Fixed the display of cashback for complex calculations.
* Removed the alternative server.

= 7.3.0 - 2025-08-14 =
* Added translation of the text "Total" in the points write-off form.
* Fixed the output of cashback for unregistered users.
* Fixed the output of cashback in woocommerce blocks.

= 7.2.2 - 2025-08-10 =
* Fixed cashback output when using points.
* Fixed compatibility with Yandex Delivery plugin (Boxberry) for Woocommerce

= 7.2.1 - 2025-07-19 =
* Fixed a bug when updating plugins.
* Optimized statistics.

= 7.2.0 - 2025-06-19 =
* Eliminated the possibility of re-awarding points.
* Added information about accrual of points to the order editor.
* Fixed the problem of correct accrual of points when changing the order status remotely.
* Fixed a type error in the bonuses personal account.


= 7.1.5 - 2025-06-15 =
* Fixed the error of resetting the user status.
* Removed the ability to write off 0 points.
* Fixed minor layout errors.


= 7.1.3 - 2025-05-25 =
* Fixed filter for accrual of bonus points on birthday.

= 7.1.2 - 2025-05-25 =
* Fixed the error displaying the write-off of bonus points on the iPhone in woocommerce blocks.
* Code refactoring.

= 7.1.1 - 2025-05-10 =
* Fixed a bug with bonus points for the first order.
* Added filter bfw-cashback-this-order.

= 7.1.0 - 2025-04-27 =
* Optimized export/import of points.
* Added reusable coupons for adding points.


= 7.0.0 - 2025-04-27 =
* Fixed a bug where a link to the referral system was displayed via a shortcode when the user had not reached the required order amount.
* Changed the principle of calculating cashback in an order. Now cashback is calculated not from the total order amount, but from each product separately.
* When exporting/importing, added the ability to search by phone number.
* Added a permanent cashback field in the product card editor, which does not depend on the client's status.



== Upgrade Notice ==

= 8.0.0 =
* Super update.