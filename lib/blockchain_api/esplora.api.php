<?php

/**
 * An implementation of blockchain_api that uses the blockstream.info esplora oracle
 * with single-address support.
 *
 * Supports using any blockstream.info host. Blockstream Esplora is an open-source project.
 *
 * For info about Esplora, see:
 *  + https://github.com/Blockstream/esplora
 */
class blockchain_api_esplora implements blockchain_api {

    /* esplora does not presently support multiaddr lookups
     */
    public static function service_supports_multiaddr() {
        return static::max_batch_size() > 1;
    }

    /* maximum addresses that can be looked up in a single request.
     */
    public static function max_batch_size() {
        // only 1 for now.
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

        $url_mask = "%s/address/%s";
        $url = sprintf( $url_mask, $params['esplora'], $addr );

        mylogger()->log( "Retrieving addresses metadata from $url", mylogger::debug );

        $result = httputil::http_get_retry( $url );
        $buf = $result['content'];

        if( $result['response_code'] == 404 ) {
            return array();
        }
        else if( $result['response_code'] != 200 ) {
            throw new Exception( "Got unexpected response code " . $result['response_code'] );
        }

        mylogger()->log( "Received address info from Esplora server.", mylogger::info );

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

      $balance = btcutil::int_to_btc( $info['chain_stats']['funded_txo_sum'] ) -
                    btcutil::int_to_btc( $info['chain_stats']['spent_txo_sum'] );

        return array( 'addr' => $info['address'],
                      'balance' => btcutil::btc_display_dec( $balance ),
                      'total_received' => btcutil::int_to_btc( $info['chain_stats']['funded_txo_sum'] ),
                      'total_sent' => btcutil::int_to_btc( $info['chain_stats']['spent_txo_sum'] ),
                      'used' => $info['chain_stats']['funded_txo_sum'] > 0,
                    );
    }

}
