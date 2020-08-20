<?php namespace Flowy;


class UserData {

    static function setup(){
        add_action( 'flowy_paywall_after_auth', '\Flowy\UserData::fetchUserProducts' , 10 );
        add_action( 'flowy_paywall_on_logout', '\Flowy\UserData::onLogout' , 10 );
        
    }


    static function getUserProducts(){

        if ( !empty( $_COOKIE['flowy_paywall_user_products'] )){
            return unserialize(base64_decode($_COOKIE['flowy_paywall_user_products']));
        }

        return null;
    }

    static function fetchUserProducts($auth){

        if ( !empty( Flowy::getSetting( 'fetch_user_products' ) ))
        {
            $api = new Api($auth->access_token);
            $products = base64_encode(serialize($api->products()));

            \setcookie( 'flowy_paywall_user_products', $products, time()+DAY_IN_SECONDS, '/' );
            $_COOKIE['flowy_paywall_user_products'] = $products;
         }

    }

    static function onLogout(){

        // Remove cookies
        \setcookie( 'flowy_paywall_user_products', null, -1, '/' );

    }

}


