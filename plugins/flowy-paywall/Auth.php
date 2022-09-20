<?php namespace Flowy;


class Auth {

    var $settings;

    static function initCallbackListener(){

        if ( isset($_GET['flowy_paywall_callback']) && isset($_GET['code']) && !empty( $_GET['code'] ) ){


            error_log( 'OAuth code recieved in callback.' );


            // Code included in GET
            $code = $_GET['code'];

            // Login with code
            self::doTokenLogin($code);

        }

        if ( isset( $_GET['flowy_paywall_logout_callback'] ) ){
            // Remove logout callback to prevent issues if user try to login again without refreshing the page
            self::stripCallbackUrl();
        }
        
    }

    static function getCurrentUrl(){
        return  (!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }


    static function getRedirectUrl(){
        global $wp;
        $current_url = self::getCurrentUrl();
        
        // Remove logout url to prevent logout when redirected back from login
        $current_url = remove_query_arg( 'flowy_paywall_logout',  $current_url );
        $current_url = remove_query_arg( 'flowy_paywall_login',  $current_url );
        $current_url = remove_query_arg( 'code',  $current_url );

        return add_query_arg( 'flowy_paywall_callback', '',  $current_url );
    }

    static function stripCallbackUrl(){
        global $wp;
        $current_url = self::getCurrentUrl();
        
        // Remove logout url to prevent logout when redirected back from login
        $current_url = remove_query_arg( 'flowy_paywall_callback',  $current_url );
        $current_url = remove_query_arg( 'flowy_paywall_logout_callback',  $current_url );   
        $current_url = remove_query_arg( 'code',  $current_url );

        wp_redirect( $current_url );
        exit;
    }
    
    static function getAuthorizeUrl(){
        $client_id = urlencode(Flowy::getSetting( 'client_id' ));
        $api_url = rtrim(Flowy::getSetting( 'login_url' ), '/');
        $redirect_uri = urlencode(Auth::getRedirectUrl());
        return "${api_url}/oauth/authorize?response_type=code&client_id=${client_id}&redirect_uri=${redirect_uri}";
    }

    
    static function getConnectAccountUrl(){
       
        // If successful, redirect to login endpoint to refresh login and subscription access
        $client_id = urlencode(Flowy::getSetting( 'client_id' ));
        $api_url = rtrim(Flowy::getSetting( 'login_url' ), '/');
        $error_url = urlencode(Auth::getCurrentUrl());
        $success_url = urlencode(Auth::getAuthorizeUrl());
        return "${api_url}/register?clientId=${client_id}&returnUrl=${success_url}&errorUrl=${error_url}";
    }
    

    /**
     * Redirect to sso endpoint and prompt user if not logged in
     */
    static function authorize(){       
        \wp_redirect(Auth::getAuthorizeUrl());
        exit;
    }

    /**
     * Redirect to sso endpoint, login locally if logged in but do not prompt user if not logged in
     */
    static function try_authorize(){       

        $current_url = Auth::getCurrentUrl();
        $current_url = remove_query_arg( 'flowy_paywall_notify_login_status',  $current_url );
        $current_url = remove_query_arg( 'flowy_paywall_previous_login',  $current_url );     

        $return_url = add_query_arg( 'flowy_paywall_login', '1',  $current_url ) ;

        // Set flag that we have checked login with third party to avoid loop
        $error_url = add_query_arg( 'flowy_paywall_notify_login_status', '1', $current_url );
        $error_url = add_query_arg( 'flowy_paywall_clean_url', '1', $error_url );
        

        $login_check_url = rtrim( Flowy::getSetting( 'login_url' ), '/') . '/loginCheck?clientId=' . Flowy::getSetting( 'client_id' ) . '&returnUrl=' . urlencode( $return_url ) . '&errorUrl=' . urlencode( $error_url );

       \wp_redirect( $login_check_url );
        exit;
    }

    static function logout(){
        $client_id = urlencode(Flowy::getSetting( 'client_id' ));
        $api_url = rtrim(Flowy::getSetting( 'login_url' ), '/');
        
        $redirect_uri = (Auth::getCurrentUrl());        
        $redirect_uri = remove_query_arg( 'flowy_paywall_logout',  $redirect_uri );
        $redirect_uri = add_query_arg( 'flowy_paywall_logout_callback', '',  $redirect_uri );
        $error_url = urlencode(add_query_arg( 'logout_error', 'true',  $redirect_uri ));
        $redirect_uri = urlencode($redirect_uri);

        $logout_url = "${api_url}/logout?clientId={$client_id}&returnUrl=${redirect_uri}&errorUrl=${redirect_uri}";

        // Trigger actions on logout
        do_action( 'flowy_paywall_on_logout' );
        
        wp_redirect($logout_url);
        exit;
    }

    
    static function doTokenLogin($code){

        $api_url = rtrim(Flowy::getSetting( 'login_url' ), '/');
        $client_id = urlencode(Flowy::getSetting( 'client_id' ) );
        $client_secret = urlencode(Flowy::getSetting( 'client_secret' ) );
        $redirect_uri = urlencode(Auth::getRedirectUrl());

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "${api_url}/oauth/token");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        //curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=authorization_code&code=${code}&client_id=${client_id}&client_secret=${client_secret}&redirect_uri=${redirect_uri}");

        $headers = array();
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            error_log( 'Error:' . curl_error($ch) );
        }

        curl_close($ch);
        do_action( 'flowy_paywall_after_auth', json_decode($result) );
        
    }

    


}
