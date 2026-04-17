=== Bonus for Woo ===
Contributors: calliko
Donate link: https://yoomoney.ru/to/410011302808683
Tags: loyalty, cashback, points, reward, referral
Requires at least:  5.6
Tested up to:  6.9
WC requires at least: 6.0
WC tested up to: 10.7.0
Stable tag: 8.0.0
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

= 8.0.0 - 2026-04-16 =
* Fixed an issue with incorrect point accrual for the first order.
* Improved security.
* Added standard WordPress tables to the point and coupon accrual history in the admin panel.
* Redesigned the plugin's admin panel interface.
* Added logging for other point actions.
* Updated the terms and conditions generator.
* Added a line in the personal account displaying how many points are pending accrual.
* Fixed a memory overflow error when writing off bonus points.
* Changed bonus system statistics.
* The plugin no longer cancels all cashback in the event of a partial refund.
* Fixed an error calculating the use of reusable coupons.

== Upgrade Notice ==

= 8.0.0 =
* Super update.