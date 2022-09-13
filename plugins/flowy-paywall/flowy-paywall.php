<?php
/**
 * Plugin Name: Flowy Paywall
 * Description: Add paywall functions from Flowy (https://www.flowy.se/)
 * Version: 1.1
 * Author: Viggeby Data AB
 * Author URI: https://www.viggeby.com/
 * Requires at least: 5.2
 * Requires PHP: 7.2
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

require_once( 'Api.php' );
require_once( 'Auth.php' );
require_once( 'Flowy.php' );
require_once( 'OptionsPage.php' );
require_once( 'Shortcodes.php' );
require_once( 'UserData.php' );
require_once( 'IpCheck.php' );


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

function flowy_logout(){
    \Flowy\Flowy::logout();
}

// TEST
header('Access-Control-Allow-Origin: https://localhost');
header('Access-Control-Allow-Credentials: true');
