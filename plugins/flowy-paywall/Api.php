<?php namespace Flowy;

class Api {

    var $access_token;

    function __construct($access_token){
        $this->access_token = $access_token;
    }

    function listSubscriptions(){       
       return $this->queryApi( '/v1/subscription/' );
    }

    function queryApi($uri){

        $api_url = rtrim(Flowy::instance()->getSetting( 'api_url' ), '/'); 
        $url = "${api_url}/${uri}";
        
        $opts = array('http' =>
            array(
                'method'  => 'GET',
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n" . "Authorization: Bearer {$this->access_token}\r\n" ."Accept: application/json\r\n"
            )
        );
        
        $context  = stream_context_create($opts);        
        $result = file_get_contents($url, false, $context);
        
        return json_decode( $result, TRUE );
    }
}