<?php

require_once __DIR__ . '/mylogger.class.php';
require_once __DIR__ . '/httputil.class.php';
require_once __DIR__ . '/bitcoin-php/bitcoin.inc';  // needed for btcd json-rpc api.


/* the public interface for blockchain_api service providers
 */
interface blockchain_api {
    public static function service_supports_multiaddr();
    
    public static function max_batch_size();
    
    // interface requirement: returned addresses must be in same order as args.
    public function get_addresses_info( $addr_list, $params );
}

/* a factory for blockchain_api service providers
 */
class blockchain_api_factory {
    static public function instance( $type ) {
        $type = trim($type);
        $class = 'blockchain_api_' . $type;
        $file = __DIR__ . "/blockchain_api/$type.api.php";
        try {
            require_once($file);
            return new $class;
        }
        catch ( Exception $e ) {
            throw new Exception( "Invalid api provider '$type'" );
        }
    }

    static public function instance_all() {
        // note: toshi is excluded because toshi.io is no longer available.
        // note: btcd is excluded because there is no public server and because
        //       it does not provide sent/received/balance figures.
        $types = ['insight', 'blockchaindotinfo', 'btcdotcom'];
        $instances = [];
        
        foreach( $types as $t ) {
            $instances[] = self::instance( $t );
        }
        return $instances;
    }

    static public function instance_all_multiaddr() {
        $list = self::instance_all();
        $filtered = [];
        
        foreach($list as $api) {
            if($api->service_supports_multiaddr()) {
                $filtered[] = $api;
            }
        }
        
        return $filtered;
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

