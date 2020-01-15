<?php
/**
 * Plugin Name: Flowy Paywall
 * Description: Add paywall functions from Flowy
 * Version: 1.0
 * Author: Innovator Digital Markets AB
 * Author URI: https://www.innovator.se/
 * Requires at least: 5.2
 * Requires PHP: 7.2
 */

require_once( 'Api.php' );
require_once( 'Auth.php' );
require_once( 'Flowy.php' );
require_once( 'Settings.php' );
require_once( 'OptionsPage.php' );


Flowy\Flowy::instance()->setup();

/*
Helper function for easy use
*/
function flowy_is_subscriber(){
    return Flowy\Flowy::isSubscriber();
}

function flowy_redirect_to_login(){
    \Flowy\Auth::authorize();
}

function flowy_get_login_url(){
    \Flowy\Auth::getRedirectUrl();
}
