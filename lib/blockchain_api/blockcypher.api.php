<?php

/**
 * An implementation of blockchain_api that uses the blockcypher oracle
 * with single-address support.
 *
 */
class blockchain_api_blockcypher implements blockchain_api {

    /* blockcypher does support multiaddr lookups
     */
    public static function service_supports_multiaddr() {
        return static::max_batch_size() > 1;
    }

    /* maximum addresses that can be looked up in a single request.
     */ 
    public static function max_batch_size() {
        // note: max items per batch call is 100 at blockcypher.
        // as per: https://www.blockcypher.com/dev/bitcoin/#batching
        // however API rate limit restricts this to 3 for the
        // free API.  See
        //   https://github.com/blockcypher/explorer/issues/245
        return 3;
    }

    /* retrieves normalized info for multiple addresses
     */
    public function get_addresses_info( $addr_list, $params ) {
                
        // https://api.blockcypher.com
        $url_mask = "%s/v1/btc/main/addrs/%s";
        $url = sprintf( $url_mask, $params['blockcypher'], implode(';', $addr_list ) );
        
        mylogger()->log( "Retrieving addresses metadata from $url", mylogger::debug );
        
        $result = httputil::http_get_retry( $url );
        $buf = $result['content'];
        
        if( $result['response_code'] == 404 ) {
            return array();
        }
        else if( $result['response_code'] != 200 ) {
            throw new Exception( "Got unexpected response code " . $result['response_code'] );
        }
        
        mylogger()->log( "Received address info from blockcypher server.", mylogger::info );
        
        $oracle_raw = $params['oracle-raw'];
        if( $oracle_raw ) {
            file_put_contents( $oracle_raw, $buf );
        }        
        
        $addr_list_r = json_decode( $buf, true );
        
        $oracle_json = $params['oracle-json'];
        if( $oracle_json ) {
            file_put_contents( $oracle_json, json_encode( $addr_info,  JSON_PRETTY_PRINT ) );
        }
        
        $map = [];
        foreach( $addr_list_r as $info ) {
            $normal = $this->normalize_address_info( $info );
            $addr = $normal['addr'];
            $map[$addr] = $normal;
        }
        
        return $this->ensure_same_order( $addr_list, $map );
    }
    
    /* normalizes address info to internal app format
     */
    protected function normalize_address_info( $info ) {

        return array( 'addr' => $info['address'],
                      'balance' => btcutil::btc_display( $info['balance'] ),
                      'total_received' => btcutil::btc_display( $info['total_received'] ),
                      'total_sent' => btcutil::btc_display( $info['total_sent'] ),
                      'used' => $info['total_received'] > 0,
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
