<?php namespace Flowy;

class Api {

    var $access_token;

    /** Last HTTP response code from queryApi (e.g. 200, 401). Call getLastResponseCode() after checkAccess to detect 401. */
    var $last_http_code;

    function __construct($access_token){
        $this->access_token = $access_token;
        $this->last_http_code = null;
    }

    /**
     * @return int|null Last HTTP status code from queryApi (200, 401, etc.)
     */
    function getLastResponseCode(){
        return $this->last_http_code;
    }

    function products(){
        return $this->queryApi( 'v1/customer/product', 'GET' );
    }

    /**
     * Check subscription access. Returns bool; use getLastResponseCode() to detect 401 (token invalid).
     * @param string $product
     * @param string $category_type
     * @return bool
     */
    function checkAccess( $product, $category_type ){

        $data = [
            'product'       => $product,
            'categoryType'  => $category_type
        ];
        $result = $this->queryApi( 'v1/customer/access', 'POST', $data );

        if ( $this->last_http_code === 401 || $result === null ) {
            return false;
        }
        return filter_var( isset( $result['access'] ) ? $result['access'] : false, FILTER_VALIDATE_BOOLEAN );
    }

    /**
     * Call ConnectID API. Returns decoded JSON array or null on failure. Sets last_http_code.
     * @param string $uri
     * @param string $method
     * @param array $data
     * @return array|null
     */
    function queryApi( $uri, $method = 'GET', $data = [] ){

        if ( \get_transient( 'flowy_paywall_simulate_401' ) ) {
            \delete_transient( 'flowy_paywall_simulate_401' );
            $this->last_http_code = 401;
            return [];
        }

        $api_url = rtrim( Flowy::getSetting( 'api_url' ), '/' );
        $url = $api_url . '/' . $uri;

        $headers = [
            'Authorization' => 'Bearer ' . $this->access_token,
            'Accept'        => 'application/json',
        ];

        $args = [
            'method'  => $method,
            'headers' => $headers,
            'timeout' => 15,
        ];

        if ( $method === 'POST' ) {
            $args['body'] = \json_encode( $data );
            $args['headers']['Content-Type'] = 'application/json';
        }

        $response = $method === 'POST'
            ? \wp_remote_post( $url, $args )
            : \wp_remote_get( $url, $args );

        $this->last_http_code = \wp_remote_retrieve_response_code( $response );
        $body = \wp_remote_retrieve_body( $response );

        if ( $body === '' || $this->last_http_code < 200 || $this->last_http_code >= 300 ) {
            return $this->last_http_code === 401 ? [] : null;
        }

        $decoded = \json_decode( $body, true );
        return \is_array( $decoded ) ? $decoded : null;
    }
}