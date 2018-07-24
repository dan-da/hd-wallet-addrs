<?php

/**
 * An implementation of blockchain_api that uses the toshi oracle.
 *
 * Supports using any toshi host. Toshi is an open-source project.
 *
 * For info about Toshi, see:
 *  + https://toshi.io/
 *  + https://github.com/coinbase/toshi
 */
class blockchain_api_toshi implements blockchain_api {

    /* toshi does not presently support multiaddr lookups
     */
    public static function service_supports_multiaddr() {
        return static::max_batch_size() > 1;
    }

    /* maximum addresses that can be looked up in a single request.
     */ 
    public static function max_batch_size() {
        // one at a time baby
        return 1;
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

    /* retrieves normalized info for a single address.
     */
    private function get_address_info( $addr, $params ) {
        
        $url_mask = "%s/api/v0/addresses/%s";
        $url = sprintf( $url_mask, $params['toshi'], $addr );
        
        mylogger()->log( "Retrieving address info from $url", mylogger::debug );
        
        $result = httputil::http_get_retry( $url );
        $buf = $result['content'];
        $data = null;

        $oracle_raw = $params['oracle-raw'];
        if( $oracle_raw ) {
            file_put_contents( $oracle_raw, $buf );
        }
        
        if( $result['response_code'] == 404 ) {
            // toshi returns 404 if address is unused.  so we fake it.
            $data = array('balance' => 0, 'received' => 0, 'sent' => 0);
        }
        else if( $result['response_code'] != 200 ) {
            throw new Exception( "Got unexpected response code " . $result['response_code'] );
        }

        mylogger()->log( "Received address info from toshi server.", mylogger::info );

        if( !$data ) {
            $data = json_decode( $buf, true );
        }
        
        $oracle_json = $params['oracle-json'];
        if( $oracle_json ) {
            file_put_contents( $oracle_json, json_encode( $data,  JSON_PRETTY_PRINT ) );
        }
        
        return $this->normalize( $data, $addr );
    }

    /* normalizes address info to internal app format
     */
    protected function normalize( $info, $addr ) {

        return array( 'addr' => $addr,
                      'balance' => btcutil::btc_display( $info['balance'] ),
                      'total_received' => btcutil::btc_display( $info['received'] ),
                      'total_sent' => btcutil::btc_display( $info['sent'] ),
                      'used' => $info['received'] > 0,
                    );
    }
}
