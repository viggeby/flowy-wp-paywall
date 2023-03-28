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

        add_action( 'wp_enqueue_scripts', [ $this, 'add_front_end_scripts' ] );

        add_action( 'init', function(){
            Auth::initCallbackListener();
        
            if(isset($_GET['flowy_paywall_try_login'])){
                Auth::try_authorize();
            }

            if(isset($_GET['flowy_paywall_login'])){
                Auth::authorize();
            }

            if(isset($_GET['flowy_paywall_logout'])){
                Flowy::logout();
            }

            // Set flag that we have checked with Idp if user is logged in
            if ( isset($_GET['flowy_paywall_notify_login_status']) ){
                Flowy::setThirdPartyLoginStatus( true );
            }

            // Listen for previous login with flag so we can set this across domains with request in case of multi-domain installations
            if ( isset($_GET['flowy_paywall_previous_login']) ){
                $previous_login = boolval( $_GET['flowy_paywall_previous_login'] );
                Flowy::setPreviousLoginCookie( $previous_login );
            }

            // Stop execution if requested
            if (isset($_GET['flowy_paywall_no_redirect'])){
                exit;
            }
           
            // Remove callback params to make URLs nice and clean for visitors, analytics etc
            if (isset($_GET['flowy_paywall_clean_url'])){
                Flowy::clean_url();
                exit;
            }


            if ( !is_admin() ) {
                // Check if IP address is on allow-list
                IpCheck::doIpCheck();
            }
        });

        // Handle the response
        add_action( 'flowy_paywall_after_auth', [ $this, 'checkSubscriptionWithApi' ] , 10 );

        // Clear callback parameters after auth to keep urls nice and clean in browser
        add_action( 'flowy_paywall_after_auth', '\Flowy\Auth::stripCallbackUrl', 999 );
        

    }

    static function clean_url(){
        $current_url = Auth::getCurrentUrl();
        $current_url = remove_query_arg( 'flowy_paywall_previous_login',  $current_url );
        $current_url = remove_query_arg( 'flowy_paywall_notify_login_status',  $current_url );
        $current_url = remove_query_arg( 'flowy_paywall_clean_url',  $current_url );
        
        \wp_redirect($current_url);
    }


    static function is_wp_login_page(){
        return $GLOBALS['pagenow'] === 'wp-login.php';
    }

    function add_front_end_scripts(){

        wp_register_script( 'flowy-auth', plugin_dir_url( __FILE__ ) . 'js/flowy-auth.js', array(), '1.0.0', true );
        wp_localize_script( 'flowy-auth', 'flowy_auth',
		[
            "login_url"                     =>  Auth::getAuthorizeUrl(),
            "get_try_authorize_url"         =>  Auth::get_try_authorize_url(),
        ]);
        wp_enqueue_script( 'flowy-auth' );
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

        // Keep actual value in transient to avoid front-end tampering
        \set_transient( "flowy_paywall_${uniqid}", $is_subscriber ? 'access' : 'noaccess', $expiration );

        // Make cookie readable during this request to avoid reload to make it available
        $_COOKIE['flowy_paywall'] = $uniqid;

        // Set cookie to show that we have checked login status with identity provider
        Flowy::setThirdPartyLoginStatus(true);

        // Set flag for checking if we can assume user have an account
        Flowy::setPreviousLoginCookie(true);

    }

    

    /**
     * Set cross domain cookie in a way that works on php5+
     */
    static function setCrossDomainCookie( $name, $value, $expires ){

        $expires_string = date( 'D, d M Y H:i:s e', $expires );

        \header("Set-Cookie: ${name}=${value}; path=/; Expires=${expires_string}; SameSite=None; Secure;", false);

        // Make available during this request
        $_COOKIE[$name] = $value;
    }

    static function setPreviousLoginCookie( $previous_login ){

        $expires = boolval( $previous_login ) ? time()+MONTH_IN_SECONDS : -1;
            
        Flowy::setCrossDomainCookie( 'flowy_paywall_previous_login', $previous_login, $expires);      
    }

    static function getPreviousLoginCookie(){

        if ( !isset($_COOKIE['flowy_paywall_previous_login']) ){
            return null;
        }

        return boolval( $_COOKIE['flowy_paywall_previous_login'] );  
    }

    static function setThirdPartyLoginStatus( $is_logged_in){

        // Create a server side transiet and match with cookie
        $expires = boolval( $is_logged_in ) ?  HOUR_IN_SECONDS*24 : -1;

        Flowy::setCrossDomainCookie( 'flowy_paywall_third_party_login', $is_logged_in,  time()+$expires );
    }

    /**
     * Check if we have tried to login with Idp (primarily used from javascript)
     *
     * @return void
     */
    static function getThirdPartyLoginStatus(){

        if ( !isset($_COOKIE['flowy_paywall_third_party_login']) ){
            return null;
        }

        return boolval( $_COOKIE['flowy_paywall_third_party_login'] );
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
            \setcookie( 'flowy_paywall_previous_login', null, -1, '/' );
            
            unset( $_COOKIE['flowy_paywall'] );
            unset( $_COOKIE['flowy_paywall_previous_login'] );

            // NOTE: Keep flowy_paywall_third_party_login so we know the user actually signed out on purpose

            // Send logout request to external provider
            Auth::logout();            

        }

    }
    
}