<?php namespace Flowy;

class Shortcodes {


    static function register(){

        add_shortcode('flowy_subscriber_only',      '\Flowy\Shortcodes::subscriberOnly');
        add_shortcode('flowy_non_subscriber',       '\Flowy\Shortcodes::nonSubscriber');
        add_shortcode('flowy_logged_in',            '\Flowy\Shortcodes::loggedIn');
        add_shortcode('flowy_not_logged_in',        '\Flowy\Shortcodes::notLoggedIn');
        add_shortcode('flowy_subscriber_name',      '\Flowy\Shortcodes::subscriberName');
        add_shortcode('flowy_buy_link',             '\Flowy\Shortcodes::buyLink');
        add_shortcode('flowy_login_link',           '\Flowy\Shortcodes::loginLink');
        add_shortcode('flowy_connect_account_link', '\Flowy\Shortcodes::connectAccountLink');
        add_shortcode('flowy_logout_link',          '\Flowy\Shortcodes::logoutLink');

    }

    static function subscriberOnly($atts, $content = null)
    {
        if ( Flowy::isSubscriber() && !is_null( $content ) )
        {
            return do_shortcode($content);
        }
        return '';
    }

    static function nonSubscriber($atts, $content)
    {
        if ( !Flowy::isSubscriber() && !is_null( $content ) ) {
            return do_shortcode($content);
        }       
        return '';
    }

    static function loggedIn($atts, $content)
    {
        if ( Flowy::isLoggedIn() && !is_null( $content ) ) {
            return do_shortcode($content);
        }       
        return '';
    }

    static function notLoggedIn($atts, $content)
    {
        if ( !Flowy::isLoggedIn() && !is_null( $content ) ) {
            return do_shortcode($content);
        }       
        return '';
    }

    static function subscriberName($atts, $content)
    {

        // TODO: Check previous implementation, seems to be broken from before.

        if ( !Flowy::isSubscriber() && !is_null( $content ) ) {
            return do_shortcode($content);
        }       
        return '';
    }

    static function buyLink($atts, $content = null, $tag='')
    {

        $buy_url = rtrim( \Flowy\Flowy::instance()->getSetting( 'buy_url' ), '/' );

        $a = shortcode_atts( array(
            'name' => '',
            'returnto' => '',
        ), $atts );


        $atts = array_change_key_case( (array)$a , CASE_LOWER );
        $name = $a["name"];
        $returnto = $a["returnto"];

        $url = "{$buy_url}/{$name}?returnUrl={$returnto}";
        $html = "<a href=\"${url}\">${content}</a>";

        return do_shortcode( $html );
    }

    static function loginLink($atts, $content = null, $tag='')
    {

        $a = shortcode_atts( array(
            'returnto' => '',
        ), $atts );

        // TODO: Implement returnto
        //$returnto = $a["returnto"];
        $login_url = \Flowy\Auth::getAuthorizeUrl();

        $html = "<a href=\"${login_url}\">${content}</a>";

        return do_shortcode( $html );
    }

    

    static function connectAccountLink($atts, $content = null, $tag='')
    {

        $a = shortcode_atts( array(
            'returnto' => '',
        ), $atts );

        // TODO: Implement returnto
        //$returnto = $a["returnto"];
        $login_url = \Flowy\Auth::getConnectAccountUrl();

        $html = "<a href=\"${login_url}\">${content}</a>";

        return do_shortcode( $html );
    }

    static function logoutLink($atts, $content = null, $tag='')
    {

        $a = shortcode_atts( array(
            'returnto' => '',
        ), $atts );

        $logout_url = \Flowy\Auth::getCurrentUrl();
        $logout_url = add_query_arg( 'flowy_paywall_logout',  $logout_url );

        $html = "<a href=\"{$logout_url}\">${content}</a>";

        return do_shortcode( $html );
    }

    


}
