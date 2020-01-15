<?php namespace Flowy;


class Auth {

    var $settings;

    static function initCallbackListener(){

        if ( isset($_GET['flowy_paywall_callback']) && isset($_GET['code']) && !empty( $_GET['code'] ) ){

            // Code included in GET
            $code = $_GET['code'];

            // Login with code
            self::doTokenLogin($code);
            
        }
        
    }


    static function getRedirectUrl(){
        return get_site_url() . "?flowy_paywall_callback";
    }

    static function getAuthorizeUrl(){
        $client_id = Flowy::instance()->settings->getSetting( 'client_id' );
        $api_url = rtrim(Flowy::instance()->settings->getSetting( 'login_url' ), '/');
        $redirect_uri = Auth::getRedirectUrl();
        return "${api_url}/oauth/authorize?response_type=code&client_id=${client_id}&redirect_uri=${redirect_uri}";
    }
    

    static function authorize(){       
        wp_redirect(Auth::getAuthorizeUrl());
        exit;
    }

    
    static function doTokenLogin($code){

        $api_url = rtrim(Flowy::instance()->settings->getSetting( 'login_url' ), '/');
        $client_id = Flowy::instance()->settings->getSetting( 'client_id' );
        $client_secret = Flowy::instance()->settings->getSetting( 'client_secret' );
        $redirect_uri = Auth::getRedirectUrl();

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
