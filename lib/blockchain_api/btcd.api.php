<?php


/**
 * An implementation of blockchain_api that uses the btcd server
 * with single-address support via the searchrawtransactions API.
 *
 * Supports using any btcd host. btcd is an open-source project.
 *
 * For info about btcd, see:
 *  + https://github.com/btcsuite/btcd
 */
class blockchain_api_btcd implements blockchain_api {

    /* btcd does not presently support multiaddr lookups
     */
    public static function service_supports_multiaddr() {
        return static::max_batch_size() > 1;
    }

    /* maximum addresses that can be looked up in a single request.
     */ 
    public static function max_batch_size() {
        // 1 at a time.  :-(
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
    
    /* retrieves normalized info for a single address
     */
    protected function get_address_info( $addr, $params ) {
        
        $url = $params['btcd'];

        $rpc = new BitcoinClient( $url, false, 'BTC' );
        
        mylogger()->log( "Retrieving addresses metadata from $url", mylogger::debug );
        
        try {
            $tx_list = $rpc->searchrawtransactions( $addr, $verbose=1, $skip=0, $count=1, $vinExtra=0, $reverse=false, $filterAddr=array( $addr ) );
        }
        catch( Exception $e ) {
            // code -5 : No information available about transaction
            if( $e->getCode() != -5 ) {
                mylogger()->log_exception($e);
                mylogger()->log( "Handled exception while calling btcd::searchrawtransactions.  continuing", mylogger::warning );
            }
            $tx_list = [];
        }
        
        mylogger()->log( "Received address info from btcd server.", mylogger::info );
        
        $oracle_raw = $params['oracle-raw'];
        if( $oracle_raw ) {
            file_put_contents( $oracle_raw, $rpc->last_response() );
        }        
        
        $oracle_json = $params['oracle-json'];
        if( $oracle_json ) {
            file_put_contents( $oracle_json, json_encode( $tx_list,  JSON_PRETTY_PRINT ) );
        }
        
        return $this->normalize_address_info( $tx_list, $addr );
    }
    
    /* normalizes address info to internal app format
     */
    protected function normalize_address_info( $tx_list, $addr ) {

        return array( 'addr' => $addr,
                      'balance' => null,
                      'total_received' => null,
                      'total_sent' => null,
                      'used' => count($tx_list) > 0
                    );
    }
}    
