<?php namespace Flowy;

class Flowy {

    var $options_page;
    var $shortcodes;

    static $instance;
    static function instance(){
        if ( empty( $instance ) ) {
            $instance = new Flowy();
        }
        return $instance;
    }

    function __construct(){
        $this->options_page = new OptionsPage();
        $this->shortcodes = new Shortcodes();
    }

    function setup(){
        $this->options_page->setup();
        $this->shortcodes->register();
        $this->addFrontEndScripts();

        add_action( 'init', function(){
            Auth::initCallbackListener();
        
            if(isset($_GET['flowy_paywall_login'])){
                Auth::authorize();
            }

            if(isset($_GET['flowy_paywall_logout'])){
                Flowy::logout();
            }
        });

        add_action( 'flowy_paywall_after_auth', [ $this, 'checkSubscriptionWithApi' ] , 10 );
        add_action( 'flowy_paywall_after_auth', '\Flowy\Auth::stripCallbackUrl', 20 );
        
        
        // Notify that the user us not logged in with Flowy and no need to hammer the API
        if ( isset($_GET['flowy_paywall_notify_login_status']) ){
            $is_logged_in = ($_GET['flowy_paywall_notify_login_status'] == true);
            Flowy::setThirdPartyLoginStatus( $is_logged_in );
        }
    }

    function addFrontEndScripts(){


        // Add front-end logic if not logged in
        if ( Flowy::getThirdPartyLoginStatus() == null) {

            add_action( 'wp_enqueue_scripts', function(){

                $api_login_check_url = rtrim( $this->getSetting( 'login_url' ), '/') . '/loginCheck?clientId=' . $this->getSetting( 'client_id' ) . '&returnUrl=' . get_home_url() . '?flowy_paywall_ajax_auth_result=true&errorUrl=' . get_home_url() . '?flowy_paywall_ajax_auth_result=false';

                wp_enqueue_script( 'flowy-paywall-api-login-check', $api_login_check_url, [], '1.0', TRUE );

                wp_enqueue_script( 'flowy-paywall-auth-check', plugin_dir_url( __FILE__ ) . '/js/auth_check.js', [ 'jquery', 'flowy-paywall-api-login-check' ], '1.0', TRUE );
                wp_localize_script( 'flowy-paywall-auth-check', "flowy_paywall", [
                    "login_url"                 =>  Auth::getAuthorizeUrl(),
                    "login_status_is_unknown"   =>  (Flowy::isLoggedIn() == null ? 'true' : 'false')
                ]);
            });
        }

    }
 
    function getSetting($name){
        return get_option( "flowy_paywall_${name}" );
    }

    function checkSubscriptionWithApi($auth){      
        
        // Ask api for list of subscriptions
        $api_product = $this->getSetting( 'api_product' );
        $api_category_type = Flowy::instance()->getSetting( 'api_category_type' );
        $api = new Api($auth->access_token);
        $result = $api->checkAccess( $api_product, $api_category_type );

        // Check if user has access
        $is_subscriber = filter_var( $result['access'], FILTER_VALIDATE_BOOLEAN );

        Flowy::doCookieAuth( $is_subscriber );

        
    }

    
    /**
     * Returns true if subscriber, false if not and null if unknown
     */
    static function isSubscriber(){

       $uniqid = Flowy::getCookie();

       if ( empty($uniqid) ){
            return null;
       }

       $transient = get_transient( "flowy_paywall_{$uniqid}" );

        if ( !empty( $transient )){
            return $transient;
        }

        return null;
    }

    /**
     * True/False if user is singed in
     */
    static function isLoggedIn(){

        // If subscriber info is not true/false but null the user is not signed in        
        return !is_null(Flowy::isSubscriber());
    }

    static function getCookie(){
        
        if ( !isset($_COOKIE['flowy_paywall']) ){
            return null;
        }

        $uniqid = $_COOKIE['flowy_paywall'];
        
        return $uniqid;
    }


    static function doCookieAuth( $is_subscriber, $uniqid = null){

        // Create a server side transiet and match with cookie
        $uniqid = $uniqid ?? \uniqid();
        $expiration = HOUR_IN_SECONDS*24;

        \setcookie( 'flowy_paywall', $uniqid, time()+$expiration, '/' );
        \set_transient( "flowy_paywall_${uniqid}", $is_subscriber, $expiration );

        // Make cookie readable during this request to avoid reload to make it available
        $_COOKIE['flowy_paywall'] = $uniqid;

        Flowy::setThirdPartyLoginStatus(true);

    }

    static function setThirdPartyLoginStatus( $is_logged_in){

        // Create a server side transiet and match with cookie
        $expiration = HOUR_IN_SECONDS*24;

        \setcookie( 'flowy_paywall_third_party_login', $is_logged_in, time()+$expiration, '/' );

        // Make cookie readable during this request to avoid reload to make it available
        $_COOKIE['flowy_paywall_third_party_login'] = $is_logged_in;

    }

    static function getThirdPartyLoginStatus(){

        if ( !isset($_COOKIE['flowy_paywall_third_party_login']) ){
            return null;
        }

        return $_COOKIE['flowy_paywall_third_party_login'];
    }


    static function logout(){

        $uniqid = Flowy::getCookie();

        if ( !empty($uniqid) ){
             
            // Set cookie to false so we know status but return false
            Flowy::doCookieAuth( false, $uniqid );

            // Send logout request to external provider
            Auth::logout();

            // Update third party status hint
            Flowy::setThirdPartyLoginStatus(false);

        }

    }
    
}