<?php

class httputil {

    /**
     * Parses the http code from $http_response_header, which is set in local
     * scope after calling file_get_contents($url)
     *
     * see: http://php.net/manual/en/reserved.variables.httpresponseheader.php
     */
    static public function http_response_header_http_code( $http_response_header ) {
        $response = @$http_response_header[0];
        if( !$response ) {
            throw new Exception( "No response from server.", 1001 );
        }
        // string should look like "HTTP/1.1 200 OK"
        // see: http://stackoverflow.com/questions/1442504/parsing-http-status-code
        $parts = explode( ' ', $response );
        $code = @$parts[1];
        if( !is_numeric( $code ) || !(int)$code ) {
            throw new Exception( "Response from server not understood.  response: $response" );
        }
        return (int)$code;
    }

}
