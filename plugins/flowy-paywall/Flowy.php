<?php namespace Flowy;

class Flowy {

    var $settings;
    var $options_page;


    static $instance;
    static function instance(){
        if ( empty( $instance ) ) {
            $instance = new Flowy();
        }
        return $instance;
    }

    function __construct(){
        $this->settings = new Settings();
        $this->options_page = new OptionsPage();

    }

    function setup(){
        $this->options_page->setup();

        add_action( 'flowy_paywall_after_auth', [ $this, 'checkSubscriptionWithApi' ] , 10 );
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
        $is_subscriber = \in_array( $this->settings->getSetting( 'subscription_name' ), $subscription_names);

        $this->setCookie( $is_subscriber );
    }


    static function isSubscriber(){

        if ( !isset($_COOKIE['flowy_paywall']) ){
            return false;
        }

        $uniqid = $_COOKIE['flowy_paywall'];

        $transient = get_transient( "flowy_paywall_{$uniqid}" );

        if ( !empty( $transient )){
            return $transient;
        }

        return false;
    }

    function setCookie( $is_subscriber ){

        // Create a server side transiet and match with cookie
        $uniqid = \uniqid();
        $expiration = HOUR_IN_SECONDS*24;

        \setcookie( 'flowy_paywall', $uniqid, time()+$expiration );
        \set_transient( "flowy_paywall_${uniqid}", $is_subscriber, $expiration );

        // Make cookie readable during this request to avoid reload to make it available
        $_COOKIE['flowy_paywall'] = $uniqid;

    }

    
}