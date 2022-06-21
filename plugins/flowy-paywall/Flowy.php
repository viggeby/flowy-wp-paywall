<?php namespace Flowy;

class Flowy {

    var $options_page;

    static $instance;
    static function instance(){
        if ( empty( $instance ) ) {
            $instance = new Flowy();
        }
        return $instance;
    }

    function __construct(){
        $this->options_page = new OptionsPage();
    }

    function setup(){
        $this->options_page->setup();
        Shortcodes::register();
        UserData::setup();
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
        add_action( 'flowy_paywall_after_auth', '\Flowy\Auth::stripCallbackUrl', 999 );
        
        
        // Notify that the user us not logged in with Flowy and no need to hammer the API
        if ( isset($_GET['flowy_paywall_notify_login_status']) ){
            $is_logged_in = ($_GET['flowy_paywall_notify_login_status'] == true);
            Flowy::setThirdPartyLoginStatus( $is_logged_in );
        }

        if ( !is_admin() ) {
            // Check if IP address is on allow-list
           IpCheck::doIpCheck();
        }
    }

    function addFrontEndScripts(){


        // Add front-end logic if not logged in
        if ( Flowy::getThirdPartyLoginStatus() == null) {

            add_action( 'wp_enqueue_scripts', function(){

                $api_login_check_url = rtrim( Flowy::getSetting( 'login_url' ), '/') . '/loginCheck?clientId=' . Flowy::getSetting( 'client_id' ) . '&returnUrl=' . get_home_url() . '?flowy_paywall_ajax_auth_result=true&errorUrl=' . get_home_url() . '?flowy_paywall_ajax_auth_result=false';

                wp_enqueue_script( 'flowy-paywall-api-login-check', $api_login_check_url, [], '1.0', TRUE );

                wp_enqueue_script( 'flowy-paywall-auth-check', plugin_dir_url( __FILE__ ) . '/js/auth_check.js', [ 'jquery', 'flowy-paywall-api-login-check' ], '1.0', TRUE );
                wp_localize_script( 'flowy-paywall-auth-check', "flowy_paywall", [
                    "login_url"                 =>  Auth::getAuthorizeUrl(),
                    "login_status_is_unknown"   =>  (Flowy::isLoggedIn() == null ? 'true' : 'false')
                ]);
            });
        }

    }
 
    static function getSetting($name){
        return get_option( "flowy_paywall_${name}" );
    }

    function checkSubscriptionWithApi($auth){      
        

        if(empty($auth)){
            error_log( 'Auth token returned from login is emtpy.' );
            wp_die('Login failed for unkown reason. Please try again.');
        }

        // Ask api for list of subscriptions
        $api_product = Flowy::getSetting( 'api_product' );
        $api_category_type = Flowy::getSetting( 'api_category_type' );
        $api = new Api($auth->access_token);


        $api_products = explode(',', $api_product); 

        // Check access on all products until one returns true.
        $is_subscriber = false;
        foreach( $api_products as $api_product ){
            $is_subscriber = $api->checkAccess( trim( $api_product ), $api_category_type );

            // Break if the user has access to avoid unnessecary calls to the API
            if( $is_subscriber )
            {
                break;
            }
        }          
        
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

        if ( $transient  != false ){     
            return $transient == "access" ? true : false;
        }

        return null;
    }

    /**
     * True/False if user is singed in
     */
    static function isLoggedIn(){

        // If subscriber info is not true/false but null the user is not signed in        
        return Flowy::isSubscriber() !== null;
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

        // Transient must be a string, non-existing will be returned as false by wp.
        \setcookie( 'flowy_paywall', $uniqid, time()+$expiration, '/' );
        \set_transient( "flowy_paywall_${uniqid}", $is_subscriber ? 'access' : 'noaccess', $expiration );

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
             
            // Remove transient
            if( isset($_COOKIE['flowy_paywall']) ){
                \delete_transient( $_COOKIE['flowy_paywall'] );
            }

            // Unset cooies
            \setcookie( 'flowy_paywall', null, -1, '/' );
            \setcookie( 'flowy_paywall_third_party_login', null, -1, '/' );
           

            // Send logout request to external provider
            Auth::logout();            

        }

    }
    
}