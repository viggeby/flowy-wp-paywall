=== Flowy Paywall ===
Contributors: jstensved
Plugin Name: Flowy Paywall
Tags: flowy
Description: Add paywall functions from Flowy (https://www.flowy.se/)
Version: 1.2
Author: Viggeby Data AB
Author URI: https://www.viggeby.com/
Requires at least: 5.2
Requires PHP: 7.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Stable tag: 1.2
Tested up to: 6.4


This plugin adds paywall functionality to WordPress with login by Flowy (flowy.se) and a verification that the user have access to a subscription configured in the admin panel.

== Description ==

This plugin adds paywall functionality to WordPress with login by Flowy and a verification that the user have access to a subscription configured in the admin panel.

The plugin does not, by design, interact with the WordPress authentication system but does instead set a cookie that is matched by a server-side transient. Session and token cache have a lifetime of 30 days. The plugin re-validates with the ConnectID API (with throttling); on 401 it uses the refresh token to obtain a new access token. If refresh fails, the user must log in again.

The previous "loginCheck" redirect (connectid.no) is no longer used; validation is done via the Check Access API. Existing users who were logged out daily due to the 24-hour limit or broken loginCheck will now stay logged in for up to 30 days or until the API indicates the token is invalid.

== Screenshots ==

1. This screen shot description corresponds to screenshot-1.(png|jpg|jpeg|gif). Note that the screenshot is stored in the /assets directory.
2. This is the second screen shot

== Frequently Asked Questions ==

= What do I need to use this plugin? =

You will need an account along with a ClientId and Secret provided from your representative at Flowy.

= How do I ask the API for additional information on behalf of the user? =

If you need to interact with the Flowy API after authentication on behalf of the user, for instance to fetch additional data, you can hook into the `flowy_paywall_after_auth` which is called after authentication with the authentication response as argument.

The `Auth` class calls this action right after response from the authentication call. This method is called regardless of authentication is successful or not.

Do use the resulting token you might do something like this:

    add_action( 'flowy_paywall_after_auth',  'my_theme_flowy_paywall_after_auth', 20 );

    function my_theme_flowy_paywall_after_auth( $auth ){

        // Get the access token
        $access_token = $auth->access_token;

        // TODO: Do something with access token

    }


== Changelog ==

= 1.2 =
* Session and token cache extended to 30 days (was 24 hours). Fixes "logged out every day" when loginCheck is deprecated.
* Access token and refresh token are stored and used for re-validation; on API 401 the plugin tries refresh_token before invalidating the session.
* Replaced dependency on connectid.no/loginCheck with real API validation (Check Access). Session is invalidated when the API returns 401 and refresh fails.
* API calls now use wp_remote_post/wp_remote_get and expose HTTP status (401 handling).
* New shortcode: [flowy_status_panel] for status and optional API debug log (for troubleshooting).
* Bugfix: try_authorize() now uses the return value from get_try_authorize_url() (redirect was broken).
* Bugfix: setcookie() no longer passes null (PHP 8.1+ compatibility); logout and UserData cookie clearing fixed.
* Output buffering on plugin load to avoid "unexpected output" during activation (e.g. with Xdebug).

= 1.4 = 

Fixed problem with SSO not working due to cross domain cookies with Media Connect.

= 1.3 =

Added IP-allow list to allow all users from an IP to bypass paywall

= 1.2 =

* Updated login logic to avoid redirect loop when using `?flowy_paywall_login` to force new login
* Consolidated readme in repo.

= 1.1 =

Bugfixes and improvements

= 1.0 =
* First release of Flowy Paywall

== A brief Markdown Example ==

## General

# Shortcodes

The shortcodes wraps content and are used by placing your content between an opening and closing shortcode.

### [flowy_subscriber_only]

    [flowy_subscriber_only]
    This content will only be shown for verified subscribers.
    [/flowy_subscriber_only] 
    
### [flowy_non_subscriber]

    [flowy_non_subscriber]
    This content will only be shown users that are not subscribers or where status is unknown. Intended to be used for showing the paywall with both login and buy links.
    [/flowy_non_subscriber]

### [flowy_buy_link]
    [flowy_buy_link]
    This content will be wrapped in `<a></a>` pointing to the URL for buying a subscription including redirect to the current page.
    [/flowy_buy_link]

### [flowy_login_link]

    [flowy_login_link]
    This content will be wrapped in `<a></a>` pointing to the URL for login with redirect back to the current page.
    [/flowy_login_link]

### [flowy_logout_link]

    [flowy_logout_link]
    This content will be wrapped in `<a></a>` pointing to the URL for logout with redirect back to the current page.
    [/flowy_logout_link]

### flowy_connect_account_link

    [flowy_connect_account_link]
    This content will be wrapped in `<a></a>` pointing to the URL for connecting a logged in user with their account if subscriptions doesn't match.
    [/flowy_connect_account_link]

### flowy_logged_in

    [flowy_logged_in]
    This content will only be shown users that are logged in, regardless if they have access or not. Usually used with `[flowy_connect_account_link]`
    [/flowy_logged_in]

### flowy_logged_in

    [flowy_not_logged_in]
    This content will only be shown users that are NOT logged in.
    [/flowy_not_logged_in]


## Examples

Show a message for logged in users without a subscription but with an existing account:

    [flowy_logged_in][flowy_non_subscriber]
    <p>You are logged in but do not seem to have a subscription linked to your account</p>

    <p>[flowy_connect_account_link]Click here to connect your subscription to your account.[/flowy_connect_account_link]</p>

    <p>[flowy_buy_link]Click here to subscribe[/flowy_buy_link]</p>

    [/flowy_non_subscriber][/flowy_logged_in]


## Technical

This plugin uses the namespace `Flowy` and the primary class is named `Flowy` with most static methods but also a default instance accessible by calling `\Flowy\Flowy::instance()`.

The default instance holds references to options page and shortcodes whereas the most methods are static and require no instance.

Authentication and interaction with the Flowy API are performed in the `Auth` class while session and cookie handling in WordPress are handled in the `Flowy` class.

## Helper functions

There are som basic helper functions to align with WordPress naming convention in templates and code but the general recommendation is to use the shortcodes described above by calling the `do_shortcode()` in WordPress.


### The following helper functions are declared

    function flowy_is_subscriber(){
        return Flowy\Flowy::isSubscriber();
    }

    function flowy_redirect_to_login(){
        \Flowy\Auth::authorize();
    }

    function flowy_get_login_url(){
        \Flowy\Auth::getRedirectUrl();
    }

    function flowy_logout(){
        \Flowy\Flowy::logout();
    }


#### Fetching user products
To fetch the array with user products after enabling the option "Fetch user products" in settings you can user the following snippet in code to retrieve the data:

    $products =  \Flowy\UserData::getUserProducts();

If the option is off or the user is not logged in `getUserProducts()` will return `NULL`.

## Action hooks


### After authentication 

If you need to interact with the Flowy API after authentication on behalf of the user, for instance to fetch additional data, you can hook into the `flowy_paywall_after_auth` which is called after authentication with the authentication response as argument.

The `Auth` class calls this action right after response from the authentication call. This method is called regardless of authentication is successful or not.

Do use the resulting token you might do something like this:

    add_action( 'flowy_paywall_after_auth',  'my_theme_flowy_paywall_after_auth', 20 );

    function my_theme_flowy_paywall_after_auth( $auth ){

        // Get the access token
        $access_token = $auth->access_token;

        // TODO: Do something with access token

    }


#### 

Enabling SSO across multiple domains.

If you have multiple domains where you wish your users to experience a seamless login you can notify these domains that the user has previously logged in and we can peform an SSO attempt for that user on their first visit. 

To tell www.domain-b.com that the user is signed in and trigger SSO from www.domain-a.com do the following:

1. Make sure your recieving domain (domain-b.com) are sending the appropriate CORS-headers:

        header('Access-Control-Allow-Origin: https://www.domain-a.com);
        header('Access-Control-Allow-Credentials: true');

2. From the browser on (www.domain-a.com), send a fetch request with the `flowy_paywall_previous_login=1` query parameter. 
Make sure to allow credentials to let cookies be set across different domains and add the flag `flowy_paywall_no_redirect` along with `flowy_paywall_third_party_login` to prevent redirects from script and making sure not previous login attempts has failed on the other domain.

        fetch('https://www.domain-b.com?flowy_paywall_previous_login=1&flowy_paywall_notify_login_status=0&flowy_paywall_no_redirect', {credentials: 'include'}).then(x => console.log('SSO request sent.'))

3. Setup the same configuration on the other domain and reverse the domain names to create a mutual login status exchange