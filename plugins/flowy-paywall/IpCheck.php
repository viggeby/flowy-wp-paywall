<?php namespace Flowy;


class IpCheck {

    static function doIpCheck(){

        $allowList = explode( ' ', Flowy::getSetting( 'ip_allow_list' ) );

        if ( in_array( $_SERVER['REMOTE_ADDR'], $allowList ) )
        {

            Flowy::doCookieAuth( true );
            Flowy::setThirdPartyLoginStatus( true );
            Flowy::setPreviousLoginCookie( true );

        }
    }

}