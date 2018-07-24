<?php

/****************************************
 * blockr.io is no longer available.  killed by coinbase.
 * https://www.ccn.com/blockr-io-shuttered-by-coinbase/
 *
 * For now keeping this class in case the API resurfaces
 * at another URL.
 *
 * R.I.P.
 */

/**
 * An implementation of blockchain_api that uses the blockr.io API
 * with multi-address support.
 *
 * For info about blockr.io, see:
 *  + http://blockr.io/documentation/api
 */
class blockchain_api_blockr implements blockchain_api {
    const MAX_ADDRS = 20;

    /* blockr.io does support multiaddr lookups
     */
    public static function service_supports_multiaddr() {
        return static::max_batch_size() > 1;
    }

    /* maximum addresses that can be looked up in a single request.
     */ 
    public static function max_batch_size() {
        // no limit
        return PHP_INT_MAX;
    }

    /* retrieves normalized info for multiple addresses
     */
    public function get_addresses_info( $addr_list, $params ) {
        
        // blockr limits addresses to 20 per query, so we batch them up
        // if necessary.
        $results = [];
        while( count($addr_list)) {
            $batch = count( $addr_list ) > self::MAX_ADDRS ?
                        array_splice( $addr_list, 0, self::MAX_ADDRS ) :
                        array_splice( $addr_list, 0, count($addr_list) );

            $r = $this->get_addresses_info_worker( $batch, $params );
            $results = array_merge( $results, $r );
        }
        return $results;
    }
    
    private function get_addresses_info_worker( $addr_list, $params ) {
        
        $url_mask = "%s/api/v1/address/info/%s";
        $url = sprintf( $url_mask, $params['blockr'], implode(',', $addr_list ) );
        
        mylogger()->log( "Retrieving addresses metadata from $url", mylogger::debug );

        $result = httputil::http_get_retry( $url );
        $buf = $result['content'];
        
        if( $result['response_code'] == 404 ) {
            return array();
        }
        else if( $result['response_code'] != 200 ) {
            throw new Exception( "Got unexpected response code " . $result['response_code'] );
        }
        
        mylogger()->log( "Received address info from blockr server.", mylogger::info );
        
        $oracle_raw = $params['oracle-raw'];
        if( $oracle_raw ) {
            file_put_contents( $oracle_raw, $buf );
        }        
        
        $response = json_decode( $buf, true );
        
        if( @$response['status'] != 'success' ) {
            throw new Exception( "Got unexpected status from blockr.io API: " . @$response['status'] );
        }
        
        $oracle_json = $params['oracle-json'];
        if( $oracle_json ) {
            file_put_contents( $oracle_json, json_encode( $response,  JSON_PRETTY_PRINT ) );
        }
        
        
        $data = $response['data'];
        
        // data may be a single object if only one address returned, or an array if multiple.
        // we normalize to an array.
        if( @$data['address'] ) {
            $data = [$data];
        }
        
        $addr_list_r = $data;
                
        $map = [];
        foreach( $addr_list_r as $info ) {
            $normal = $this->normalize_address_info( $info );
            $addr = $normal['addr'];
            $map[$addr] = $normal;
        }
        
        // addresses sometimes come back in different order than we sent them.  :(
        return $this->ensure_same_order( $addr_list, $map );
    }

    /* retrieves normalized info for a single address
     */
    protected function normalize_address_info( $info ) {
        
        $total_sent = btcutil::btc_to_int( $info['totalreceived'] ) -
                      btcutil::btc_to_int( $info['balance'] );

        return array( 'addr' => $info['address'],
                      'balance' => btcutil::btc_display_dec( $info['balance'] ),
                      'total_received' => btcutil::btc_display_dec( $info['totalreceived'] ),
                      'total_sent' => btcutil::btc_display( $total_sent ),
                      'used' => $info['totalreceived'] > 0,
                    );
    }
    
    protected function ensure_same_order( $addrs, $response ) {
        $new_response = array();
        foreach( $addrs as $addr ) {
            $new_response[] = $response[$addr];
        }
        return $new_response;
    }
}
