<?php

/**
 * An implementation of blockchain_api that uses the blockchain.info
 * oracle with multi-address support.
 */
class blockchain_api_blockchaindotinfo implements blockchain_api {

    /* blockchain.info does support multiaddr lookups
     */
    public static function service_supports_multiaddr() {
        return static::max_batch_size() > 1;
    }

    /* maximum addresses that can be looked up in a single request.
     */ 
    public static function max_batch_size() {
        // limit unknown.  let's use 1000.
        return 1000;
    }
    
    /* retrieves normalized info for multiple addresses
     */
    public function get_addresses_info( $addr_list, $params ) {
        
        $url_mask = "%s/multiaddr?active=%s";
        $url = sprintf( $url_mask, $params['blockchaindotinfo'], implode('|', $addr_list ) );
        
        mylogger()->log( "Retrieving addresses metadata from $url", mylogger::debug );
        
        $result = httputil::http_get_retry( $url );
        $buf = $result['content'];
        
        if( $result['response_code'] == 404 ) {
            return array();
        }
        else if( $result['response_code'] != 200 ) {
            throw new Exception( "Got unexpected response code " . $result['response_code'] );
        }
        
        mylogger()->log( "Received address info from blockchaindotinfo server.", mylogger::info );
        
        $oracle_raw = $params['oracle-raw'];
        if( $oracle_raw ) {
            file_put_contents( $oracle_raw, $buf );
        }        
        
        $response = json_decode( $buf, true );
        $addr_list_r = $response['addresses'];
        
        $oracle_json = $params['oracle-json'];
        if( $oracle_json ) {
            file_put_contents( $oracle_json, json_encode( $response,  JSON_PRETTY_PRINT ) );
        }
        
        // addresses sometimes come back in different order than we sent them.  :(
        
        $map = [];
        foreach( $addr_list_r as $info ) {
            $normal = $this->normalize_address_info( $info );
            $addr = $normal['addr'];
            $map[$addr] = $normal;
        }
        
        return $this->ensure_same_order( $addr_list, $map );
    }

    /* retrieves normalized info for a single address
     */
    protected function normalize_address_info( $info ) {

        return array( 'addr' => $info['address'],
                      'balance' => btcutil::btc_display( $info['final_balance'] ),
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
