# Flowy Paywall Plugin for WordPress


## General

This plugin adds paywall functionality to WordPress with login by Flowy and a verification that the user have access to a subscription configured in the admin panel.

The plugin does not, by design, interact with the WordPress authentication system but does instead set a cookie that is matched by a server-side transient. This transient have a maximum lifetime of 24 hours which will after expiration force the user to re-authenticate with Flowy.

If the user is already signed-in with flowy, either with persistent cookie or with a recent sign-in, a front-end script will check if the user have access and renew the login token by a redirect if it is needed and can be done without interrupting the user.

## Install and configuration

No special steps are required outside of WordPress standard plugin activation. A configuration is needed through the options page <strong>Flowy Paywall</strong> accessible if you have `manage_options` permisssions.

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

