<?php

/**
 * An implementation of blockchain_api that uses the blockcypher oracle
 * with single-address support.
 *
 */
class blockchain_api_blockcypher implements blockchain_api {

    /* blockcypher does not presently support multiaddr lookups
     */
    public static function service_supports_multiaddr() {
        return false;
    }

    /* retrieves normalized info for multiple addresses
     */
    public function get_addresses_info( $addr_list, $params ) {
        $addrs = array();
        foreach( $addr_list as $addr ) {
            $addrs[] = $this->get_address_info( $addr, $params );;
        }
        return $addrs;
    }
    
    /* retrieves normalized info for a single address
     */
    protected function get_address_info( $addr, $params ) {
        
        // https://api.blockcypher.com
        $url_mask = "%s/v1/btc/main/addrs/%s";
        $url = sprintf( $url_mask, $params['blockcypher'], $addr );
        
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
        
        $addr_info = json_decode( $buf, true );
        
        $oracle_json = $params['oracle-json'];
        if( $oracle_json ) {
            file_put_contents( $oracle_json, json_encode( $addr_info,  JSON_PRETTY_PRINT ) );
        }
        
        return $this->normalize_address_info( $addr_info, $addr );
    }
    
    /* normalizes address info to internal app format
     */
    protected function normalize_address_info( $info, $addr ) {

        return array( 'addr' => $info['address'],
                      'balance' => btcutil::btc_display( $info['balance'] ),
                      'total_received' => btcutil::btc_display( $info['total_received'] ),
                      'total_sent' => btcutil::btc_display( $info['total_sent'] ),
                      'used' => $info['total_received'] > 0,
                    );
    }
    
}
