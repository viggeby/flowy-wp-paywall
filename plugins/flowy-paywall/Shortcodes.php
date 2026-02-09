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
        add_shortcode('flowy_status_panel',         '\Flowy\Shortcodes::statusPanel');

    }

    /**
     * Visual status panel for testing: shows login/subscriber state and action links.
     * Use [flowy_status_panel] on a page to see the full picture.
     */
    static function statusPanel( $atts, $content = null ) {

        $is_logged_in = Flowy::isLoggedIn();
        $is_subscriber = Flowy::isSubscriber();

        if ( $is_subscriber === true ) {
            $status_text = 'Inloggad + prenumerant';
            $status_color = '#0a7a42';
            $status_bg = '#e6f4ed';
        } elseif ( $is_logged_in ) {
            $status_text = 'Inloggad (ingen prenumeration)';
            $status_color = '#b5510a';
            $status_bg = '#fef4e6';
        } else {
            $status_text = 'Ej inloggad';
            $status_color = '#6b7280';
            $status_bg = '#f3f4f6';
        }

        $buy_url = rtrim( Flowy::getSetting( 'buy_url' ), '/' );
        $login_url = \Flowy\Auth::getAuthorizeUrl();
        $logout_url = add_query_arg( 'flowy_paywall_logout', '', \Flowy\Auth::getCurrentUrl() );
        $connect_url = \Flowy\Auth::getConnectAccountUrl();
        $force_refresh_url = add_query_arg( 'flowy_paywall_force_refresh', '1', \Flowy\Auth::getCurrentUrl() );
        $simulate_401_url = add_query_arg( 'flowy_paywall_simulate_401', '1', \Flowy\Auth::getCurrentUrl() );
        $simulate_expired_cookie_url = add_query_arg( 'flowy_paywall_simulate_expired_cookie', '1', \Flowy\Auth::getCurrentUrl() );
        $simulate_no_access_url = add_query_arg( 'flowy_paywall_simulate_no_access', '1', \Flowy\Auth::getCurrentUrl() );
        $clear_simulate_no_access_url = add_query_arg( 'flowy_paywall_clear_simulate_no_access', '1', \Flowy\Auth::getCurrentUrl() );

        ob_start();
        ?>
        <div class="flowy-status-panel" style="max-width:480px;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;font-family:system-ui,-apple-system,sans-serif;font-size:14px;line-height:1.5;">
            <div style="padding:12px 16px;background:<?php echo esc_attr( $status_bg ); ?>;border-bottom:1px solid #e5e7eb;">
                <strong style="color:<?php echo esc_attr( $status_color ); ?>;">Flowy Paywall – Status</strong>
                <div style="margin-top:6px;color:#374151;"><?php echo esc_html( $status_text ); ?></div>
            </div>
            <div style="padding:12px 16px;background:#fff;">
                <div style="display:flex;flex-wrap:wrap;gap:8px;">
                    <?php if ( ! $is_logged_in ) : ?>
                        <a href="<?php echo esc_url( $login_url ); ?>" style="display:inline-block;padding:8px 14px;background:#2563eb;color:#fff;text-decoration:none;border-radius:6px;">Logga in</a>
                    <?php endif; ?>
                    <?php if ( $is_logged_in ) : ?>
                        <a href="<?php echo esc_url( $logout_url ); ?>" style="display:inline-block;padding:8px 14px;background:#6b7280;color:#fff;text-decoration:none;border-radius:6px;">Logga ut</a>
                    <?php endif; ?>
                    <?php if ( $is_logged_in && ! $is_subscriber && $connect_url ) : ?>
                        <a href="<?php echo esc_url( $connect_url ); ?>" style="display:inline-block;padding:8px 14px;background:#0a7a42;color:#fff;text-decoration:none;border-radius:6px;">Koppla prenumeration</a>
                    <?php endif; ?>
                    <?php if ( $buy_url ) : ?>
                        <a href="<?php echo esc_url( $buy_url ); ?>" style="display:inline-block;padding:8px 14px;background:#f3f4f6;color:#374151;text-decoration:none;border-radius:6px;border:1px solid #d1d5db;">Köp prenumeration</a>
                    <?php endif; ?>
                </div>
                <?php if ( $is_logged_in ) : ?>
                <div style="margin-top:10px;padding-top:10px;border-top:1px solid #e5e7eb;">
                    <span style="font-size:11px;color:#6b7280;">Felsökning:</span>
                    <a href="<?php echo esc_url( $force_refresh_url ); ?>" style="margin-left:8px;font-size:11px;color:#2563eb;">Förnya token</a>
                    <a href="<?php echo esc_url( $simulate_401_url ); ?>" style="margin-left:8px;font-size:11px;color:#b45309;">Simulera 401</a>
                    <a href="<?php echo esc_url( $simulate_expired_cookie_url ); ?>" style="margin-left:8px;font-size:11px;color:#6b7280;">Utgången cookie</a>
                    <a href="<?php echo esc_url( $simulate_no_access_url ); ?>" style="margin-left:8px;font-size:11px;color:#b45309;">Ingen prenumeration</a>
                    <?php
                    $uniqid_check = Flowy::getCookie();
                    if ( $uniqid_check && get_transient( "flowy_paywall_simulate_no_access_{$uniqid_check}" ) ) :
                    ?>
                    <a href="<?php echo esc_url( $clear_simulate_no_access_url ); ?>" style="margin-left:8px;font-size:11px;color:#0a7a42;">Återställ</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php
            $uniqid = Flowy::getCookie();
            $debug = Flowy::getDebugLog( $uniqid );
            $simulated_401 = isset( $_GET['flowy_paywall_simulated_401'] );
            if ( ! empty( $debug['lines'] ) ) :
                $when = $debug['time'] ? gmdate( 'Y-m-d H:i:s', $debug['time'] ) . ' UTC' : '';
            ?>
            <div style="padding:10px 16px;background:#f8fafc;border-top:1px solid #e5e7eb;font-size:12px;color:#475569;">
                <strong>API-logg (felsökning)</strong><?php if ( $when ) : ?> – <?php echo esc_html( $when ); ?><?php endif; ?>
                <pre style="margin:8px 0 0;padding:8px;background:#fff;border:1px solid #e2e8f0;border-radius:4px;overflow:auto;max-height:120px;white-space:pre-wrap;word-break:break-word;"><?php echo esc_html( implode( "\n", $debug['lines'] ) ); ?></pre>
            </div>
            <?php elseif ( $simulated_401 ) : ?>
            <div style="padding:10px 16px;background:#fef3e6;border-top:1px solid #e5e7eb;font-size:12px;color:#92400e;">
                <strong>API-logg (felsökning)</strong>
                <pre style="margin:8px 0 0;padding:8px;background:#fff;border:1px solid #e2e8f0;border-radius:4px;">401 simulerad – session rensad för test (som vid utgången token utan lyckad refresh)</pre>
            </div>
            <?php elseif ( isset( $_GET['flowy_paywall_simulated_expired_cookie'] ) ) : ?>
            <div style="padding:10px 16px;background:#f3f4f6;border-top:1px solid #e5e7eb;font-size:12px;color:#374151;">
                <strong>API-logg (felsökning)</strong>
                <pre style="margin:8px 0 0;padding:8px;background:#fff;border:1px solid #e2e8f0;border-radius:4px;">Utgången cookie simulerad – session-cookien togs bort (som efter 30 d eller rensade cookies)</pre>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
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

        $buy_url = rtrim( Flowy::getSetting( 'buy_url' ), '/' );

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
