<?php namespace Flowy;


class Settings {

    function getSetting($name){
        return get_option( "flowy_paywall_${name}" );
    }
}