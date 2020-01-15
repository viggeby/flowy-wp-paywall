<?php namespace Flowy;

class Shortcodes {


    function register(){

        add_shortcode('flowy_subscriber_only',  [$this, 'subscriberOnly'] );
        add_shortcode('flowy_non_subscriber',   [$this, 'nonSubscriber'] );
        add_shortcode('flowy_subscriber_name',  [$this, 'subscriberName']);
        add_shortcode('flowy_buy_link',         [$this, 'buyLink']);
        add_shortcode('flowy_login_link',       [$this, 'loginLink']);
        add_shortcode('flowy_logout_link',      [$this, 'logoutLink']);

    }

    function subscriberOnly($atts, $content = null)
    {
        if ( Flowy::instance()->isSubscriber() && !is_null( $content ) )
        {
            return do_shortcode($content);
        }
        return '';
    }

    function nonSubscriber($atts, $content)
    {
        if ( !Flowy::instance()->isSubscriber() && !is_null( $content ) ) {
            return do_shortcode($content);
        }       
        return '';
    }

    function subscriberName($atts, $content)
    {

        // TODO: Check previous implementation, seems to be broken from before.

        if ( !Flowy::instance()->isSubscriber() && !is_null( $content ) ) {
            return do_shortcode($content);
        }       
        return '';
    }

    function buyLink($atts, $content = null, $tag='')
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

    function loginLink($atts, $content = null, $tag='')
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

    function logoutLink($atts, $content = null, $tag='')
    {

        $a = shortcode_atts( array(
            'returnto' => '',
        ), $atts );

        $html = "<a href=\"?flowy_paywall_logout\">${content}</a>";

        return do_shortcode( $html );
    }

    


}
