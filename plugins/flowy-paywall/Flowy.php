<?php namespace Flowy;

class Flowy {

    /** Session and token cache lifetime (30 days). Invalidated on API 401 or refresh failure. */
    const SESSION_SECONDS = 30 * DAY_IN_SECONDS;

    /** Throttle: re-validate with API at most once per 24 hours per user. */
    const REVALIDATE_THROTTLE_SECONDS = 24 * HOUR_IN_SECONDS;

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

            if ( isset( $_GET['flowy_paywall_force_refresh'] ) ) {
                Flowy::handleForceRefresh();
            }

            if ( isset( $_GET['flowy_paywall_simulate_401'] ) ) {
                Flowy::handleSimulate401();
            }

            if ( isset( $_GET['flowy_paywall_simulate_expired_cookie'] ) ) {
                Flowy::handleSimulateExpiredCookie();
            }

            if ( isset( $_GET['flowy_paywall_simulate_no_access'] ) ) {
                Flowy::handleSimulateNoAccess();
            }

            if ( isset( $_GET['flowy_paywall_clear_simulate_no_access'] ) ) {
                Flowy::handleClearSimulateNoAccess();
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

    function checkSubscriptionWithApi( $auth ){

        if ( empty( $auth ) || empty( $auth->access_token ) ) {
            error_log( 'Flowy Paywall: Auth token returned from login is empty.' );
            wp_die( 'Login failed for unknown reason. Please try again.' );
        }

        $api_product = Flowy::getSetting( 'api_product' );
        $api_category_type = Flowy::getSetting( 'api_category_type' );
        $api = new Api( $auth->access_token );
        $api_products = explode( ',', $api_product );

        $is_subscriber = false;
        foreach ( $api_products as $product ) {
            $is_subscriber = $api->checkAccess( trim( $product ), $api_category_type );
            if ( $is_subscriber ) {
                break;
            }
        }

        if ( $api->getLastResponseCode() === 401 ) {
            error_log( 'Flowy Paywall: API returned 401 on first login; token invalid.' );
            wp_die( 'Login could not be completed. Please try again.' );
        }

        $uniqid = Flowy::doCookieAuth( $is_subscriber );
        Flowy::storeTokens( $uniqid, $auth->access_token, isset( $auth->refresh_token ) ? $auth->refresh_token : null );
        \set_transient( "flowy_paywall_lastcheck_{$uniqid}", time(), Flowy::SESSION_SECONDS );

        $products = array_filter( array_map( 'trim', explode( ',', $api_product ) ) );
        Flowy::storeDebugLog( $uniqid, [
            'Första inloggning',
            'Produkter: ' . implode( ', ', $products ),
            'Kategori: ' . $api_category_type,
            'HTTP: ' . $api->getLastResponseCode(),
            'Access: ' . ( $is_subscriber ? 'ja' : 'nej' ),
        ] );
    }

    
    /**
     * Returns true if subscriber, false if not, null if unknown/session invalid.
     * Re-validates with API when throttle allows; on 401 tries refresh_token, then invalidates session if needed.
     */
    static function isSubscriber(){

        $uniqid = Flowy::getCookie();
        if ( empty( $uniqid ) ) {
            return null;
        }

        if ( \get_transient( "flowy_paywall_simulate_no_access_{$uniqid}" ) ) {
            return false;
        }

        $cached = get_transient( "flowy_paywall_{$uniqid}" );
        $access_token = Flowy::getStoredAccessToken( $uniqid );

        if ( empty( $access_token ) ) {
            return $cached !== false ? ( $cached === 'access' ) : null;
        }

        $lastcheck = get_transient( "flowy_paywall_lastcheck_{$uniqid}" );
        if ( $lastcheck !== false && ( time() - (int) $lastcheck ) < self::REVALIDATE_THROTTLE_SECONDS ) {
            return $cached === 'access';
        }

        $api_product = Flowy::getSetting( 'api_product' );
        $api_category_type = Flowy::getSetting( 'api_category_type' );
        $products = array_filter( array_map( 'trim', explode( ',', $api_product ) ) );
        $first_product = $products ? $products[0] : '';
        if ( $first_product === '' || $api_category_type === '' ) {
            return $cached === 'access';
        }

        $api = new Api( $access_token );
        $is_subscriber = false;
        foreach ( $products as $product ) {
            $is_subscriber = $api->checkAccess( $product, $api_category_type );
            if ( $is_subscriber ) break;
        }

        $http = $api->getLastResponseCode();
        $debug = [ 'Re-validering', 'Produkter: ' . implode( ', ', $products ), 'Kategori: ' . $api_category_type, 'HTTP: ' . $http, 'Access: ' . ( $is_subscriber ? 'ja' : 'nej' ) ];

        if ( $http === 200 ) {
            set_transient( "flowy_paywall_lastcheck_{$uniqid}", time(), self::SESSION_SECONDS );
            set_transient( "flowy_paywall_{$uniqid}", $is_subscriber ? 'access' : 'noaccess', self::SESSION_SECONDS );
            Flowy::storeDebugLog( $uniqid, $debug );
            return $is_subscriber;
        }

        if ( $http !== 401 ) {
            return $cached === 'access';
        }

        $refresh_token = Flowy::getStoredRefreshToken( $uniqid );
        $new_auth = $refresh_token ? Auth::refreshAccessToken( $refresh_token ) : null;
        if ( $new_auth && ! empty( $new_auth->access_token ) ) {
            Flowy::storeTokens( $uniqid, $new_auth->access_token, isset( $new_auth->refresh_token ) ? $new_auth->refresh_token : null );
            $api = new Api( $new_auth->access_token );
            $is_subscriber = false;
            foreach ( $products as $product ) {
                $is_subscriber = $api->checkAccess( $product, $api_category_type );
                if ( $is_subscriber ) break;
            }
            $http2 = $api->getLastResponseCode();
            $debug[] = 'Refresh: ny token';
            $debug[] = 'HTTP (efter refresh): ' . $http2;
            $debug[] = 'Access: ' . ( $is_subscriber ? 'ja' : 'nej' );
            if ( $http2 === 200 ) {
                set_transient( "flowy_paywall_lastcheck_{$uniqid}", time(), self::SESSION_SECONDS );
                set_transient( "flowy_paywall_{$uniqid}", $is_subscriber ? 'access' : 'noaccess', self::SESSION_SECONDS );
                Flowy::storeDebugLog( $uniqid, $debug );
                return $is_subscriber;
            }
        } else {
            $debug[] = 'Refresh: misslyckad eller ingen refresh_token';
        }

        Flowy::storeDebugLog( $uniqid, $debug );
        Flowy::invalidateSession( $uniqid );
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


    /**
     * Create session (cookie + transients). Returns uniqid for storing tokens.
     * @param bool $is_subscriber
     * @param string|null $uniqid
     * @return string uniqid
     */
    static function doCookieAuth( $is_subscriber, $uniqid = null){

        $uniqid = $uniqid ?? \uniqid();
        $expiration = self::SESSION_SECONDS;

        \setcookie( 'flowy_paywall', $uniqid, time() + $expiration, '/' );
        \set_transient( "flowy_paywall_{$uniqid}", $is_subscriber ? 'access' : 'noaccess', $expiration );

        $_COOKIE['flowy_paywall'] = $uniqid;

        Flowy::setThirdPartyLoginStatus(true);
        Flowy::setPreviousLoginCookie(true);

        return $uniqid;
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

        $expires = boolval( $is_logged_in ) ? self::SESSION_SECONDS : -1;
        Flowy::setCrossDomainCookie( 'flowy_paywall_third_party_login', $is_logged_in, time() + $expires );
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


    /**
     * Store access_token and optional refresh_token for a session (30d TTL).
     * @param string $uniqid
     * @param string $access_token
     * @param string|null $refresh_token
     */
    static function storeTokens( $uniqid, $access_token, $refresh_token = null ){

        if ( empty( $uniqid ) || empty( $access_token ) ) {
            return;
        }
        \set_transient( "flowy_paywall_token_{$uniqid}", $access_token, self::SESSION_SECONDS );
        if ( $refresh_token !== null && $refresh_token !== '' ) {
            \set_transient( "flowy_paywall_refresh_token_{$uniqid}", $refresh_token, self::SESSION_SECONDS );
        }
    }

    /** @return string|null */
    static function getStoredAccessToken( $uniqid ){
        if ( empty( $uniqid ) ) return null;
        $t = \get_transient( "flowy_paywall_token_{$uniqid}" );
        return $t === false ? null : $t;
    }

    /** @return string|null */
    static function getStoredRefreshToken( $uniqid ){
        if ( empty( $uniqid ) ) return null;
        $t = \get_transient( "flowy_paywall_refresh_token_{$uniqid}" );
        return $t === false ? null : $t;
    }

    /** Debug log TTL (5 min) – only for status panel / felsökning */
    const DEBUG_LOG_TTL = 300;

    /**
     * Store debug log lines for this session (product, API response, etc.). Overwrites previous.
     * @param string $uniqid
     * @param array $lines List of strings
     */
    static function storeDebugLog( $uniqid, array $lines ) {
        if ( empty( $uniqid ) ) return;
        $data = [ 'time' => time(), 'lines' => $lines ];
        \set_transient( "flowy_paywall_debug_{$uniqid}", $data, self::DEBUG_LOG_TTL );
    }

    /**
     * Get last stored debug log for this session (for status panel).
     * @param string|null $uniqid
     * @return array [ 'time' => int, 'lines' => string[] ] or empty
     */
    static function getDebugLog( $uniqid ) {
        if ( empty( $uniqid ) ) return [ 'time' => 0, 'lines' => [] ];
        $data = \get_transient( "flowy_paywall_debug_{$uniqid}" );
        return \is_array( $data ) && isset( $data['lines'] ) ? $data : [ 'time' => 0, 'lines' => [] ];
    }

    /**
     * Debug: force token refresh (anropar refresh_token utan att vänta på 401).
     * Redirectar till aktuell URL utan query-param.
     */
    static function handleForceRefresh() {
        $uniqid = self::getCookie();
        if ( empty( $uniqid ) ) {
            return;
        }
        $refresh_token = self::getStoredRefreshToken( $uniqid );
        if ( empty( $refresh_token ) ) {
            return;
        }
        $new_auth = Auth::refreshAccessToken( $refresh_token );
        if ( $new_auth && ! empty( $new_auth->access_token ) ) {
            self::storeTokens( $uniqid, $new_auth->access_token, isset( $new_auth->refresh_token ) ? $new_auth->refresh_token : null );
            \delete_transient( "flowy_paywall_lastcheck_{$uniqid}" );
            self::storeDebugLog( $uniqid, [ 'Debug: Forcerad token-förnyelse', 'Resultat: ny access_token sparad' ] );
        } else {
            self::storeDebugLog( $uniqid, [ 'Debug: Forcerad token-förnyelse', 'Resultat: refresh misslyckades' ] );
        }
        $url = remove_query_arg( 'flowy_paywall_force_refresh', Auth::getCurrentUrl() );
        wp_redirect( $url );
        exit;
    }

    /**
     * Debug: simulerar 401 – rensar session direkt så du ser "Ej inloggad" (samma resultat som 401 utan refresh).
     */
    static function handleSimulate401() {
        $uniqid = self::getCookie();
        if ( ! empty( $uniqid ) ) {
            self::storeDebugLog( $uniqid, [
                'Debug: Simulera 401',
                'Session rensad (som vid 401 utan lyckad refresh)',
            ] );
            self::invalidateSession( $uniqid );
        }
        $url = remove_query_arg( 'flowy_paywall_simulate_401', Auth::getCurrentUrl() );
        $url = add_query_arg( 'flowy_paywall_simulated_401', '1', $url );
        wp_redirect( $url );
        exit;
    }

    /**
     * Debug: simulerar utgången cookie – tar bort session-cookien så nästa request ser "Ej inloggad".
     */
    static function handleSimulateExpiredCookie() {
        \setcookie( 'flowy_paywall', '', time() - 3600, '/' );
        unset( $_COOKIE['flowy_paywall'] );
        $url = remove_query_arg( 'flowy_paywall_simulate_expired_cookie', Auth::getCurrentUrl() );
        $url = add_query_arg( 'flowy_paywall_simulated_expired_cookie', '1', $url );
        wp_redirect( $url );
        exit;
    }

    /**
     * Debug: simulerar "inloggad men ingen prenumeration" – isSubscriber() returnerar false i 120 s.
     */
    static function handleSimulateNoAccess() {
        $uniqid = self::getCookie();
        if ( ! empty( $uniqid ) ) {
            \set_transient( "flowy_paywall_simulate_no_access_{$uniqid}", 1, 120 );
        }
        $url = remove_query_arg( 'flowy_paywall_simulate_no_access', Auth::getCurrentUrl() );
        wp_redirect( $url );
        exit;
    }

    /**
     * Debug: slutar simulera "ingen prenumeration".
     */
    static function handleClearSimulateNoAccess() {
        $uniqid = self::getCookie();
        if ( ! empty( $uniqid ) ) {
            \delete_transient( "flowy_paywall_simulate_no_access_{$uniqid}" );
        }
        $url = remove_query_arg( 'flowy_paywall_clear_simulate_no_access', Auth::getCurrentUrl() );
        wp_redirect( $url );
        exit;
    }

    /**
     * Invalidate local session (e.g. on API 401 or refresh failure). Does not redirect to ConnectID logout.
     * @param string $uniqid Session uniqid from cookie
     */
    static function invalidateSession( $uniqid ){

        if ( empty( $uniqid ) ) {
            return;
        }

        \delete_transient( "flowy_paywall_{$uniqid}" );
        \delete_transient( "flowy_paywall_token_{$uniqid}" );
        \delete_transient( "flowy_paywall_refresh_token_{$uniqid}" );
        \delete_transient( "flowy_paywall_lastcheck_{$uniqid}" );
        \delete_transient( "flowy_paywall_debug_{$uniqid}" );

        \setcookie( 'flowy_paywall', '', time() - 3600, '/' );
        unset( $_COOKIE['flowy_paywall'] );
    }

    static function logout(){

        $uniqid = Flowy::getCookie();

        if ( !empty($uniqid) ){

            \delete_transient( "flowy_paywall_{$uniqid}" );
            \delete_transient( "flowy_paywall_token_{$uniqid}" );
            \delete_transient( "flowy_paywall_refresh_token_{$uniqid}" );
            \delete_transient( "flowy_paywall_lastcheck_{$uniqid}" );

            \setcookie( 'flowy_paywall', '', time() - 3600, '/' );
            \setcookie( 'flowy_paywall_previous_login', '', time() - 3600, '/' );

            unset( $_COOKIE['flowy_paywall'] );
            unset( $_COOKIE['flowy_paywall_previous_login'] );

            // NOTE: Keep flowy_paywall_third_party_login so we know the user actually signed out on purpose

            Auth::logout();
        }
    }

}