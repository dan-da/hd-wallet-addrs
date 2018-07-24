<?php

/**
 * An implementation of blockchain_api that uses the btc.com
 * oracle with multi-address support.
 */
class blockchain_api_btcdotcom implements blockchain_api  {

    /* blockchain.info does support multiaddr lookups
     */
    public static function service_supports_multiaddr() {
        return static::max_batch_size() > 1;
    }

    /* maximum addresses that can be looked up in a single request.
     */ 
    public static function max_batch_size() {
        // unknown.  let's use 1000 so we don't get too crazy.
        return 1000;
    }
    

    /* retrieves normalized info for multiple addresses
     */
    public function get_addresses_info( $addr_list, $params ) {

        $url_mask = "%s/v3/address/%s";
        $url = sprintf( $url_mask, $params['btcdotcom'], implode(',', $addr_list ) );
        
        mylogger()->log( "Retrieving addresses metadata from $url", mylogger::debug );
        
        $result = httputil::http_get_retry( $url );
        $buf = $result['content'];
        
        if( $result['response_code'] == 404 ) {
            return array();
        }
        else if( $result['response_code'] != 200 ) {
            throw new Exception( "Got unexpected response code " . $result['response_code'] );
        }
        
        mylogger()->log( "Received address info from btcdotcom server.", mylogger::info );
        
        $oracle_raw = $params['oracle-raw'];
        if( $oracle_raw ) {
            file_put_contents( $oracle_raw, $buf );
        }        
        
        $response = json_decode( $buf, true );
        $addr_list_r = $response['data'];

        $oracle_json = $params['oracle-json'];
        if( $oracle_json ) {
            file_put_contents( $oracle_json, json_encode( $response,  JSON_PRETTY_PRINT ) );
        }        
        
        $map = [];
        foreach( $addr_list as $i => $addr ) {
            $info = $addr_list_r[$i];
            if($info == null) {
                $info = ['address' => $addr, 'received' => '0', 'sent' => 0, 'balance' => 0];
            }
            $normal = $this->normalize_address_info( $info );
            $addr = $normal['addr'];
            $map[$addr] = $normal;
        }
        
        // just in case addrs ever come back in different order than we sent them.
        return $this->ensure_same_order( $addr_list, $map );
    }

    /* retrieves normalized info for a single address
     */
    protected function normalize_address_info( $info ) {

        return array( 'addr' => $info['address'],
                      'balance' => btcutil::btc_display( $info['balance'] ),
                      'total_received' => btcutil::btc_display( $info['received'] ),
                      'total_sent' => btcutil::btc_display( $info['sent'] ),
                      'used' => $info['received'] > 0,
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
