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

        if ( isset($_GET['flowy_paywall_ajax_auth_result']) ){

            $result = $_GET['flowy_paywall_ajax_auth_result'];
            echo "window.flowy_paywall_ajax_auth_result = ${result};
                (function($){ 
                    $(window).trigger('flowy_paywall_ajax_auth_result', window.flowy_paywall_ajax_auth_result);
                })(jQuery);";
            exit;

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
    

    static function authorize(){       
        wp_redirect(Auth::getAuthorizeUrl());
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
            echo 'Error:' . curl_error($ch);
        }
        curl_close($ch);
        do_action( 'flowy_paywall_after_auth', json_decode($result) );
        
    }

    


}
