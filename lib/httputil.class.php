<?php

class httputil {

    /**
     *  make an http GET request and return the response content and headers
     *   @param string $url    url of the requested script
     *   @return returns a hash array with response content and headers in the following form:
     *       array ('content'=>'<html></html>',
     *             'headers'=>array ('HTTP/1.1 200 OK', 'Connection: close', ...)
     *             )
     */
    static public function http_get($url)
    {
        $content = @file_get_contents ( $url );
        $code = self::http_response_header_http_code( @$http_response_header );
    
        return array ('content' => $content,
                      'headers' => $http_response_header,
                      'response_code' => $code);
    }

    static public function http_get_retry($url, $max_retry=3) {
        
        $loglines = [];
        $try = 1;
        do {
            try {
                 
                $loglines[] = "making get request to $url";
                $data = self::http_get($url);
                $response_code = $data['response_code'];
                $loglines[] = "get request completed with http response code $response_code";
                $data['log'] = $loglines;
                return $data;
            }
            catch( Exception $e ) {
                $response_code = 1;
                $loglines[] = "caught exception during get request.\n   " . $e->getMessage();
            }
          sleep(1);
        } while( $code != 200 && $try++ <= $max_retry );

        throw new Exception( "Max retry of 3 reached.  http get failed.\n\nLog:\n" . implode( "\n", $loglines) );
    }

    
    /**
     *  make an http POST request and return the response content and headers
     *   @param string $url    url of the requested script
     *   @param array $data    hash array of request variables
     *   @return returns a hash array with response content and headers in the following form:
     *       array ('content'=>'<html></html>',
     *             'headers'=>array ('HTTP/1.1 200 OK', 'Connection: close', ...)
     *             )
     */
    static public function http_post ($url, $data)
    {
        $data_url = http_build_query ($data);
        return self::http_post_raw( $url, $data_url );
    }

    /**
     *  make an http POST request and return the response content and headers
     *   @param string $url    url of the requested script
     *   @param string $data   raw data to be sent.
     *   @return returns a hash array with response content and headers in the following form:
     *       array ('content'=>'<html></html>',
     *             'headers'=>array ('HTTP/1.1 200 OK', 'Connection: close', ...)
     *             )
     */
    static public function http_post_raw ($url, $data_raw)
    {
        $data_len = strlen ($data_raw);
        $context = stream_context_create ( array ('http' => array ('method'       => 'POST',
                                                                   'header'       => "Connection: close\r\nContent-Length: $data_len\r\nContent-Type: application/x-www-form-urlencoded",
                                                                   'content'      => $data_raw
                                                                  )
                                                 )
                                         );
    
        $content = @file_get_contents ($url, false, $context);
        $code = self::http_response_header_http_code( $http_response_header );
    
        return array ('content' => $content,
                      'headers' => $http_response_header,
                      'response_code' => $code);
    }
    
    
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
