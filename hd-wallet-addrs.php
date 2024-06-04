#!/usr/bin/env php
<?php

/**
 * Entry point for hd-wallet-addrs.
 *
 * Code in this file is related to interacting with the shell.
 */
require_once __DIR__  . '/vendor/autoload.php';

 // Add our DIR to include_path, so vendor will load if we are invoked from another dir.
 set_include_path(get_include_path() . ':' . __DIR__);

// Let's be strict about things.
// require_once __DIR__ . '/lib/strict_mode.funcs.php';

// This guy does the heavy lifting.
require_once __DIR__ . '/lib/walletaddrs.class.php';

/**
 * Call main and exit with return code.
 */
exit(main($argv));

/**
 * Our main function.  It performs top-level exception handling.
 */
function main( $argv ) {
    // why limit ourselves?    ;-)
    ini_set('memory_limit', -1 );

    try {
        list( $params, $success ) = process_cli_params( get_cli_params( $argv ));
        if( $success != 0 ) {
            return $success;
        }

        $worker = new walletaddrs( $params );
        $addrs = $worker->discover_wallet_addrs( get_xpub_list($params) );
        walletaddrsreport::print_results($worker->get_params(), $addrs);

        return 0;
    }
    catch( Exception $e ) {
        mylogger()->log_exception( $e );

        // print validation errors to stderr.
        if( $e->getCode() == 2 ) {
            fprintf( STDERR, $e->getMessage() . "\n\n" );
        }
        return $e->getCode() ?: 1;
    }
}

/* returns the CLI params, exactly as entered by user.
 */
function get_cli_params() {
    $params = getopt( 'g', array( 'xpub:', 'xpubfile:',
                                  'outfile:',
                                  'derivation:',
                                  'numsig:',
                                  'format:', 'cols:',
                                  'gap-limit:',
                                  'logfile:', 'loglevel:',
                                  'toshi:',
                                  'blockchaindotinfo:',
                                  'btcd:',
                                  'btcdotcom:',
                                  'blockcypher:',
                                  'insight:',
                                  'esplora:',
                                  'api:',
                                  'oracle-raw:', 'oracle-json:',
                                  'include-unused',
                                  'include:',
                                  'gen-only:', 'type:',
                                  'batch-size:',
                                  'version', 'help',
                                  ) );

    return $params;
}

/* processes and normalizes the CLI params. adds defaults
 * and ensure each value is set.
 */
function process_cli_params( $params ) {
    $success = 0;   // 0 == success.

    if( isset( $params['version'] ) ) {
        print_version();
        return [$params, 2];
    }
    if( isset( $params['help']) || !isset($params['g']) ) {
        print_help(false);
        return [$params, 1];
    }


    if( @$params['logfile'] ) {
        mylogger()->set_log_file( $params['logfile'] );
        mylogger()->echo_log = false;
    }

    $loglevel = @$params['loglevel'] ?: 'info';
    mylogger()->set_log_level_by_name( $loglevel );

    $xpublist = get_xpub_list( $params, $empty_ok = true );

    $params['derivation'] = @$params['derivation'] ?: 'relative';
    $params['include'] = isset($params['include-unused']) && !@$params['include'] ? 'both' : @$params['include'];
    $params['include'] = @$params['include'] ?: 'used';   // used, unused, or both.
    if( !in_array( $params['include'], ['used', 'unused', 'both'] )) {
        throw new Exception('--include must be one of [used, unused, both]');
    }

    $params['multisig'] = count($xpublist) > 1;

    // legacy copay used multisig even for 1 of 1 wallets.
    if( $params['derivation'] == 'copaylegacy' ) {
        $params['multisig'] = true;
        // if numsig is missing for 1of1 then we set it to 1.
        $params['numsig'] = @$params['numsig'] ?: (count($xpublist)==1 ? 1 : null);
    }

    $params['gen-only'] = is_numeric( @$params['gen-only'] ) ? $params['gen-only'] : null;

    $types = array( 'receive', 'change', 'both');
    $params['type'] = in_array( @$params['type'], $types ) ? $params['type'] : 'both';

    if( count($xpublist) > 1 && !@$params['numsig'] ) {
        throw new Exception( "multisig requires --numsig" );
    }

    $params['api'] = @$params['api'] ?: 'blockchaindotinfo';

    // no default url for btcd
    if( $params['api'] == 'btcd' && !@$params['btcd'] ) {
        throw new Exception( "btcd url must be provided in form http://user:pass@host:port.  https ok also");
    }

    $params['gap-limit'] = @$params['gap-limit'] ?: 20;
    $params['batch-size'] = @$params['batch-size'] ?: 'auto';
    $params['cols'] = get_cols( $params );

    $params['insight'] = @$params['insight'] ?: 'https://insight.bitpay.com/api';
    $params['esplora'] = @$params['esplora'] ?: 'https://blockstream.info/api';
    $params['btcd'] = @$params['btcd'];
    $params['blockchaindotinfo'] = @@$params['blockchaindotinfo'] ?: 'https://blockchain.info';
    $params['btcdotcom'] = @@$params['btcdotcom'] ?: 'https://chain.api.btc.com';
    $params['blockcypher'] = @@$params['blockcypher'] ?: 'https://api.blockcypher.com';
    $params['toshi'] = @$params['toshi'] ?: 'https://bitcoin.toshi.io';

    $params['format'] = @$params['format'] ?: 'txt';
    $params['cols'] = @$params['cols'] ?: 'all';

    $params['oracle-raw'] = @$params['oracle-raw'] ?: null;
    $params['oracle-json'] = @$params['oracle-json'] ?: null;

    return [$params, $success];
}

/**
 * prints program version text
 */
function print_version() {
    $version = @file_get_contents(  __DIR__ . '/VERSION');
    echo $version ?: 'version unknown' . "\n";
}


/* prints CLI help text
 */
function print_help($stderr = true) {

    $levels = mylogger()->get_level_map();
    $allcols = implode(',', walletaddrs::all_cols() );
    $defaultcols = implode(',', walletaddrs::default_cols() );

    $loglevels = implode(',', array_values( $levels ));

    $buf = <<< END

   hd-wallet-addrs.php

   This script discovers bitcoin HD wallet addresses that have been used.

   Options:

    -g                   go!  ( required )

    --xpub=<csv>         comma separated list of xpub keys
    --xpubfile=<path>    file containing xpub keys, one per line.
                           note: multiple keys implies multisig m of n.

    --derivation=<type>  bip32|bip44|bip45|copaylegacy|relative.
                           default=relative
    --numsig=<int>       number of required signers for m-of-n multisig wallet.
                           (required for multisig)

    --gap-limit=<int>    bip32 unused addr gap limit. default=20
    --include=<type>     include which addresses.  one of [used, unused, both]
                         note that unused addresses are subject to --gap-limit
    --include-unused     equivalent to --include=both

    --gen-only=<n>      will generate n receive addresses and n change addresses
                          but will not query the blockchain to determine if they
                          have been used.

    --type=<type>       receive|change|both.  default=both

    --api=<api>          [toshi|insight|blockchaindotinfo|btcd|
                          btcdotcom|roundrobin]
                           default = blockchaindotinfo  (fastest)
                           roundrobin will use a different API for each batch
                            to improve privacy.  It also sets --batch-size to
                            1 if set to auto.

    --batch-size=<n>    integer|auto   default=auto.
                          The number of addresses to lookup in each batch.

    --cols=<cols>        a csv list of columns, or "all"
                         all:
                          ($allcols)
                         default:
                          ($defaultcols)

    --outfile=<path>     specify output file path.
    --format=<format>    txt|csv|json|jsonpretty|html|addrlist|all   default=txt

                         if all is specified then a file will be created
                         for each format with appropriate extension.
                         only works when outfile is specified.

    --toshi=<url>       toshi server. defaults to https://bitcoin.toshi.io
    --insight=<url>     insight server.  defaults to https://insight.bitpay.com/api
    --blockcypher=<url> blockcypher      defaults to https://api.blockcypher.com

    --blockchaindotinfo=<url>
                        blockchain.info server.  defaults to https://blockchain.info

    --btcd=<url>        btcd rpc server.  specify as http://user:pass@host:port.  https ok also
                          btcd does not return balance or total sent/received.

    --oracle-raw=<p>    path to save raw server response, optional.
    --oracle-json=<p>   path to save formatted server response, optional.

    --logfile=<file>    path to logfile. if not present logs to stdout.
    --loglevel=<level>  $loglevels
                          default = info



END;

   fprintf( $stderr ? STDERR : STDOUT, $buf );

}

/* parses the --cols argument and returns an array of columns.
 */
function get_cols( $params ) {
    $arg = strip_whitespace( @$params['cols'] ?: null );

    $allcols = walletaddrs::all_cols();

    if( $arg == 'all' ) {
        $cols = $allcols;
    }
    else if( !$arg ) {
        $cols = $params['gen-only'] ?  walletaddrs::default_cols_gen_only() : walletaddrs::default_cols();
    }
    else {
        $cols = explode( ',', $arg );
        foreach( $cols as $c ) {
            if( !in_array($c, $allcols) ) {
                throw new Exception( "'$c' is not a known report column.", 2 );
            }
        }
    }

    return $cols;
}


/* obtains the xpub keys from user input, either via the
 * --xpub arg or the --xpubfile arg.
 */
function get_xpub_list($params, $empty_ok=false) {

    $list = array();
    if( @$params['xpub'] ) {
        $list = explode( ',', strip_whitespace( $params['xpub'] ) );
    }
    if( @$params['xpubfile'] ) {
        $csv = implode( ',', file( $params['xpubfile'] ) );
        $list = explode( ',', strip_whitespace( $csv ) );
    }
    foreach( $list as $idx => $xpub ) {
        if( !$xpub ) {
            unset( $list[$idx] );
            continue;
        }
        // todo: validate length/form.
        if( !is_valid_xpub( $xpub ) ) {
            // code 2 means an input validation exception.
            throw new Exception( "xpub is invalid: $xpub", 2 );
        }
    }
    if( !count( $list ) && !$empty_ok) {
        // code 2 means an input validation exception.
        throw new Exception( "No valid xpub keys to process.", 2 );
    }
    return $list;
}

/* Returns true if xpub is valid
 */
function is_valid_xpub($xpub) {
    return true;
    // TODO: better validation.
    //  Q: what lengths can xpub be?
    $needle = 'xpub';
    $len = strlen($xpub);
    return $len > 100 && $len < 120 && substr( $xpub, 0, strlen($needle) ) == $needle;
}

/* removes whitespace from a string
 */
function strip_whitespace( $str ) {
    return preg_replace('/\s+/', '', $str);
}
