<?php

require_once __DIR__ . '/mylogger.class.php';
require_once __DIR__ . '/httputil.class.php';

/* the public interface for blockchain_api service providers
 */
interface blockchain_api {
    public function service_supports_multiaddr();
    
    // interface requirement: returned addresses must be in same order as args.
    public function get_addresses_info( $addr_list, $params );
}

/* a factory for blockchain_api service providers
 */
class blockchain_api_factory {
    static public function instance( $type ) {
        $type = trim($type);
        $class = 'blockchain_api_' . $type;
        try {
            return new $class;
        }
        catch ( Exception $e ) {
            throw new Exception( "Invalid api provider '$type'" );
        }
    }
    
    static public function instance_all() {
        $types = ['toshi', 'insight', 'blockchaindotinfo', 'blockr'];
        $instances = [];
        
        foreach( $types as $t ) {
            $instances[] = self::instance( $t );
        }
        return $instances;
    }
    
}

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
    public function service_supports_multiaddr() {
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

    /* retrieves normalized info for a single address.
     */
    private function get_address_info( $addr, $params ) {
        
        $url_mask = "%s/api/v0/addresses/%s";
        $url = sprintf( $url_mask, $params['toshi'], $addr );
        
        mylogger()->log( "Retrieving address info from $url", mylogger::debug );
        
        $result = httputil::http_get( $url );
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
                    );
    }
    
}


/**
 * An implementation of blockchain_api that uses the insight oracle
 * with single-address support.
 *
 * Supports using any insight host. insight is an open-source project.
 *
 * For info about insight, see:
 *  + https://github.com/bitpay/insight
 */
class blockchain_api_insight  {

    /* insight does not presently support multiaddr lookups
     */
    public function service_supports_multiaddr() {
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
        
        $url_mask = "%s/api/addr/%s/?noTxList=1";
        $url = sprintf( $url_mask, $params['insight'], $addr );
        
        mylogger()->log( "Retrieving addresses metadata from $url", mylogger::debug );
        
        $result = httputil::http_get( $url );
        $buf = $result['content'];
        
        if( $result['response_code'] == 404 ) {
            return array();
        }
        else if( $result['response_code'] != 200 ) {
            throw new Exception( "Got unexpected response code " . $result['response_code'] );
        }
        
        mylogger()->log( "Received address info from insight server.", mylogger::info );
        
        $oracle_raw = $params['oracle-raw'];
        if( $oracle_raw ) {
            file_put_contents( $oracle_raw, $buf );
        }        
        
        $addr_info = json_decode( $buf, true );
        
        $oracle_json = $params['oracle-json'];
        if( $oracle_json ) {
            file_put_contents( $oracle_json, json_encode( $data,  JSON_PRETTY_PRINT ) );
        }
        
        return $this->normalize_address_info( $addr_info, $addr );
    }
    
    /* normalizes address info to internal app format
     */
    protected function normalize_address_info( $info, $addr ) {

        return array( 'addr' => $info['addrStr'],
                      'balance' => btcutil::btc_display_dec( $info['balance'] ),
                      'total_received' => btcutil::btc_display_dec( $info['totalReceived'] ),
                      'total_sent' => btcutil::btc_display_dec( $info['totalSent'] ),
                    );
    }
    
}


/**
 * An implementation of blockchain_api that uses the insight oracle
 * with single-address support.
 *
 * Supports using any insight host. insight is an open-source project.
 *
 * For info about insight, see:
 *  + https://github.com/bitpay/insight
 */
class blockchain_api_blockchaindotinfo  {

    /* blockchain.info does support multiaddr lookups
     */
    public function service_supports_multiaddr() {
        return true;
    }

    /* retrieves normalized info for multiple addresses
     */
    public function get_addresses_info( $addr_list, $params ) {
        
        $url_mask = "%s/multiaddr?active=%s";
        $url = sprintf( $url_mask, $params['blockchaindotinfo'], implode('|', $addr_list ) );
        
        mylogger()->log( "Retrieving addresses metadata from $url", mylogger::debug );
        
        $result = httputil::http_get( $url );
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
            file_put_contents( $oracle_json, json_encode( $data,  JSON_PRETTY_PRINT ) );
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

/**
 * An implementation of blockchain_api that uses the blockr.io API
 * with multi-address support.
 *
 * For info about blockr.io, see:
 *  + http://blockr.io/documentation/api
 */
class blockchain_api_blockr  {
    const MAX_ADDRS = 20;

    /* blockr.io does support multiaddr lookups
     */
    public function service_supports_multiaddr() {
        return true;
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

        $result = httputil::http_get( $url );
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
            file_put_contents( $oracle_json, json_encode( $data,  JSON_PRETTY_PRINT ) );
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



/* a utility class for btc and fiat conversions.
 */
class btcutil {
    
    const SATOSHI = 100000000;
    const CENT = 100;
    
    /* converts btc decimal amount to integer amount.
     */
    static public function btc_to_int( $val ) {
        return (int)round($val * self::SATOSHI, 0);
    }

    /* converts btc integer amount to decimal amount with full precision.
     */
    static public function int_to_btc( $val ) {
        return $val / self::SATOSHI;
    }

    /* formats btc integer amount for display as decimal amount (rounded)
     */
    static public function btc_display( $val ) {
        return number_format( round( $val / self::SATOSHI, 8 ), 8, '.', '');
    }

    /* formats btc decimal amount for display as decimal amount (rounded)
     */
    static public function btc_display_dec( $val ) {
        return number_format( round( $val, 8 ), 8, '.', '');
    }
    
    /* formats usd integer amount for display as decimal amount (rounded)
     */
    static public function fiat_display( $val ) {
        return number_format( round( $val / self::CENT, 2 ), 2, '.', '');
    }

    /* converts btc integer amount to decimal amount with full precision.
     */
    static public function btcint_to_fiatint( $val ) {
        return (int)round($val / self::SATOSHI, 0);
    }
    
}


