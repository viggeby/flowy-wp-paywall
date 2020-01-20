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
    }

    function addFrontEndScripts(){

        add_action( 'wp_enqueue_scripts', function(){
            wp_enqueue_script( 'flowy-paywall-auth-check', plugin_dir_url( __FILE__ ) . '/js/auth_check.js', [ 'jquery' ], '1.0', TRUE );
            wp_localize_script( 'flowy-paywall-auth-check', "flowy_paywall", [
                "login_url"     =>  $this->getSetting( 'login_url' ) ,
                "return_url"    =>  Auth::getRedirectUrl(),
                "auth_url"      =>  Auth::getAuthorizeUrl(),
                "client_id"     =>  $this->getSetting( 'client_id' )               
            ]);
        });

    }

    function getSetting($name){
        return get_option( "flowy_paywall_${name}" );
    }

    function checkSubscriptionWithApi($auth){

        // Ask api for list of subscriptions
        $api = new Api($auth->access_token);
        $result = $api->listSubscriptions();

        // Extract subscription names
        $subscription_names = array_map( function($item){
            return $item[ 'product' ];
        }, $result[ 'subscriptions' ] );

        // Check if names contains subscription name in settings to match
        $is_subscriber = \in_array( $this->getSetting( 'subscription_name' ), $subscription_names);

        $this->doCookieAuth( $is_subscriber );
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
    static function isSignedIn(){

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


    function doCookieAuth( $is_subscriber ){

        // Create a server side transiet and match with cookie
        $uniqid = \uniqid();
        $expiration = HOUR_IN_SECONDS*24;

        \setcookie( 'flowy_paywall', $uniqid, time()+$expiration );
        \set_transient( "flowy_paywall_${uniqid}", $is_subscriber, $expiration );

        // Make cookie readable during this request to avoid reload to make it available
        $_COOKIE['flowy_paywall'] = $uniqid;

    }


    static function logout(){

        $uniqid = Flowy::getCookie();

        if ( !empty($uniqid) ){
             
            // Remove cookie by expiring it
            setcookie( 'flowy_paywall', time() - 3600 );

            // Delete transient
            delete_transient( "flowy_paywall_{$uniqid}" );

        }

    }
    
}