=== Bonus for Woo ===
Contributors: calliko
Donate link: https://yoomoney.ru/to/410011302808683
Tags: loyalty, cashback, points, reward, referral
Requires at least:  6.2
Tested up to:  7.1
WC requires at least: 6.2
WC tested up to: 11.3.0
Stable tag: 8.5.6
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
* Purchasing goods using only points.

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
* Support for the classic shopping cart and checkout process
* Support for WooCommerce Blocks cart and checkout
* With the WPML plugin
* With the Elementor plugin
* With the Yandex.Delivery (Boxberry) plugin for WooCommerce
* With the CDEK shipping method plugin for WooCommerce
* With the Post SMTP plugin
* With high-performance order storage
* Multi-site support


== SUPPORT ==
If you need support or have questions, please write to our [**support**](https://wordpress.org/support/plugin/bonus-for-woo/) or [**blog**](https://computy.ru/blog/bonus-for-woo-wordpress/#reply-title).

== Installation ==

1. Upload dir `bonus-for-woo` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress


== External services ==

This plugin connects to the plugin author's server in the following cases.

= License activation (PRO) =
The PRO key is verified against `https://computy.ru/API/api.php`. The request sends the license key, the site domain, the plugin version and the product identifier. The server returns a signed token that is stored in the site options.

* Service: computy.ru
* Data sent: license key, site domain, plugin version, product ID
* When: on activation and no more than once every 7 days
* Terms: https://computy.ru/privacy

= Buy button =
The "Buy" button on the PRO settings screen posts the site URL, the plugin version and the site administrator email to `https://computy.ru/buy_plugin`.

* Service: computy.ru
* Data sent: site URL, plugin version, admin email
* When: only when the administrator clicks the button
* Terms: https://computy.ru/privacy

= Blog articles widget =
The plugin settings screen loads the list of the author's recent articles from `https://computy.ru/blog/tag/bfw/` and caches the result in a transient.

* Service: computy.ru
* Data sent: no visitor data, an ordinary HTTP request
* When: when the plugin settings screen is opened while the cache is empty
* Terms: https://computy.ru/privacy

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

= 8.5.6 - 2026-11-17 =
* Fixed: refunding an order fully paid with points (total 0) never returned the spent points — refund share is now calculated from item subtotals for such orders.
* Fixed: cashback forecast in the cart ignored the points discount, so the cart showed a bigger cashback than was actually credited (base is now the amount after all discounts and coupons).
* Added: referral link (?bfwkey) is now saved for logged-in customers too, and the referrer is attached to the specific order (order meta _bfw_referrer_id) — a one-time reward instead of a permanent user binding. Filter: bfw_referral_order_cookie_once.
* Added: referral reward for an order can now be revoked on refund (reason referral_revoke, idempotent by refund id). Filter: bfw_referral_revoke_on_cancel.
* Changed: the write-off limit is calculated per cart item — products/categories «points only» can be covered up to 100%, the rest keeps the global max-percent-bonuses. Filter: bfw_points_only_max_percent.
* Added: statistics break down spent points by reason (purchases, burned by expiry, returned on refunds, cashback revoked).
* Added: filters bfw_referral_cookie_days, bfw_points_only_max_percent, bfw_referral_revoke_on_cancel.

= 8.5.5 - 2026-11-15 =
* Fixed a cookie-related issue that occurred when redeeming points.
* Optimized the cashback calculation for variable products.