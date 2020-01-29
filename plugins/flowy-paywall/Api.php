<?php namespace Flowy;

class Api {

    var $access_token;

    function __construct($access_token){
        $this->access_token = $access_token;
    }

    function checkAccess( $product, $category_type ){       

        $data = [
            'product'       => $product,
            'categoryType'  => $category_type
        ];
        return $this->queryApi( 'v1/customer/access', 'POST', $data );
     }

    function queryApi( $uri,  $method = 'GET', $data = []){

        $api_url = rtrim(Flowy::instance()->getSetting( 'api_url' ), '/'); 
        $url = "${api_url}/${uri}";
        
        $opts = array('http' =>
            array(
                'method'  => $method,
                'header'  => "Authorization: Bearer {$this->access_token}\r\n" ."Accept: application/json\r\n"
            )
        );

        if ( 'POST' == $method ) {
            
            $post_data = \json_encode( $data );
            $opts['http']['content'] = $post_data;
            $opts['http']['header'] .= "Content-Type: application/json\r\n" . "Content-Length: " . strlen( $post_data ) . "\r\n";


        }
        
        $context  = stream_context_create($opts);        
        $result = file_get_contents($url, false, $context);
        return json_decode( $result, TRUE );
    }
}