<?php

require_once __DIR__  . '/../vendor/autoload.php';

// For HD-Wallet Key Derivation
use \BitWasp\Bitcoin\Bitcoin;
use \BitWasp\Bitcoin\Address;
use \BitWasp\Bitcoin\Key\Deterministic\HierarchicalKeyFactory;
use \BitWasp\Buffertools\Buffer;

// For ypub/zpub support
use BitWasp\Bitcoin\Key\Deterministic\HdPrefix\GlobalPrefixConfig;
use BitWasp\Bitcoin\Key\Deterministic\HdPrefix\NetworkConfig;
use BitWasp\Bitcoin\Key\Deterministic\Slip132\Slip132;
use BitWasp\Bitcoin\Key\KeyToScript\KeyToScriptHelper;
use BitWasp\Bitcoin\Serializer\Key\HierarchicalKey\Base58ExtendedKeySerializer;
use BitWasp\Bitcoin\Serializer\Key\HierarchicalKey\ExtendedKeySerializer;
use BitWasp\Bitcoin\Network\Slip132\BitcoinRegistry;

// For address generation
use BitWasp\Bitcoin\Address\AddressCreator;

// For Copay Multisig stuff.
use \BitWasp\Bitcoin\Mnemonic\Bip39\Bip39SeedGenerator;

// For generating plaintext tables.
require_once __DIR__ . '/mysqlutil.class.php';

// For generating html tables.
require_once __DIR__ . '/html_table.class.php';

// For blockchain API calls via various providers.
require_once __DIR__ . '/blockchain_api.php';


/* A class that implements HD wallet address discovery
 */
class walletaddrs {

    // Contains options we care about.
    protected $params;
    
    const receive_idx = 0;
    const change_idx = 1;
    
    public function __construct( $params ) {
        $this->params = $params;
    }

    /* Getter for params
     */
    private function get_params() {
        return $this->params;
    }

    /* Discovers used wallet addrs for regular or multi-sig HD wallet.
     */
    public function discover_wallet_addrs($xpub_list) {
        $params = $this->get_params();
        $addrs = null;
        
        if( $params['multisig'] ) {
            $addrs = $this->discover_wallet_addrs_multisig( $xpub_list );
        }
        else {
            $addrs = $this->discover_wallet_addrs_single( $xpub_list[0] );
        }

        // sort by bip32_path to ensure they are in proper order.
        usort($addrs, function($a, $b) {
            return strnatcasecmp($a['relpath'], $b['relpath']);
        });
            
        walletaddrsreport::print_results($this->get_params(), $addrs);
    }


    /* Discovers receive and change addresses for a master xpub from
     * a multsig HD wallet.  ( eg, Copay )
     */
    protected function discover_wallet_addrs_multisig($xpub_list) {

        $key_types = [];
        $kt = null;
        foreach( $xpub_list as $pk ) {
            $k[] = $this->fromExtendedKey($pk, Bitcoin::getNetwork() );
            $kt = $this->keyTypeFromSerializedKey($pk);
            $key_types[$kt] = 1;
        }
        
        if(count($key_types) > 1) {
            throw new Exception("Cannot mix key types");
        }        
        
        $params = $this->get_params();
        $num_signers = $params['numsig'];
        
        $ec = \BitWasp\Bitcoin\Bitcoin::getEcAdapter();
        $sequences = new \BitWasp\Bitcoin\Key\Deterministic\HierarchicalKeySequence($ec->getMath());
        $hd = new \BitWasp\Bitcoin\Key\Deterministic\MultisigHD($num_signers, 'm/44', $k, $sequences, true);
        
        return $this->discover_addrs($hd, $is_multi=true, $kt);
    }

    /* Returns a map of address types.
     */
    static public function addrtypes() {
        return [self::receive_idx => 'Receive', self::change_idx => 'Change'];
    }

    // key_type is one of x,y,Y,z,Z
    private function getScriptPrefixForKeyType($key_type) {
        $adapter = Bitcoin::getEcAdapter();
        $slip132 = new Slip132(new KeyToScriptHelper($adapter));
        $coinPrefixes = new BitcoinRegistry();
        
        switch( $key_type ) {
            case 'x': $prefix = $slip132->p2pkh($coinPrefixes); break;
            case 'X': $prefix = $slip132->p2shP2pkh($coinPrefixes); break;  // also xpub.  this case won't work.
            case 'y': $prefix = $slip132->p2shP2wpkh($coinPrefixes); break;
            case 'Y': $prefix = $slip132->p2shP2wshP2pkh($coinPrefixes); break;
            case 'z': $prefix = $slip132->p2wpkh($coinPrefixes); break;
            case 'Z': $prefix = $slip132->p2wshP2pkh($coinPrefixes); break;
            default:
                throw new Exception("Unknown key type: $key_type");
        }
        
        return $prefix;
    }
    
    
    private function getSerializer($network, $key_type) {
        $adapter = Bitcoin::getEcAdapter();

        $prefix = $this->getScriptPrefixForKeyType($key_type);
        $config = new GlobalPrefixConfig([new NetworkConfig($network, [$prefix]),]);
        
        $serializer = new Base58ExtendedKeySerializer(new ExtendedKeySerializer($adapter, $config));
        return $serializer;
    }
    
    private function keyTypeFromSerializedKey($key_buf) {
        return @$key_buf[0];
    }
    
    private function fromExtendedKey($key_buf, $network) {
        $key_type = $this->keyTypeFromSerializedKey($key_buf);
        $serializer = $this->getSerializer($network, $key_type);
        return $serializer->parse($network, $key_buf);
    }

    private function toExtendedKey($key, $network, $key_type) {
        $serializer = $this->getSerializer($network, $key_type);
        return $serializer->serialize($network, $key);
    }    

    
    /* Discovers receive and change addresses for a master xpub from
     * a regular HD wallet.  ( not multisig )
     */
    protected function discover_wallet_addrs_single($x_pub_key) {
        $params = $this->get_params();
    
        $math = Bitcoin::getMath();
        $network = Bitcoin::getNetwork();

        //$x_pub_key = '1LSedCD6AFWJaZXF2qR8jZobaGx3jN6akv';
        $master = $this->fromExtendedKey($x_pub_key, $network);
        $key_type = $this->keyTypeFromSerializedKey($x_pub_key);

        /*        
        echo "Master Public Key (m)\n";
        echo "   " . $master->toExtendedPublicKey($network) . "\n";
        echo "   Address: " . $master->getPublicKey()->getAddress()->getAddress() . "\n";
        echo sprintf( "   depth: %s, sequence: %s\n\n", $master->getDepth(), $master->getSequence() );
        */
        
        return $this->discover_addrs( $master, $is_multi=false, $key_type );
    }

    /* Discovers receive and change addresses for a given xpub key.
     */
    protected function discover_addrs( $xpub, $is_multi, $key_type ) {
        $master = $xpub;

        $params = $this->get_params();
        $addrs = array();      // filtered addrs.
        $gap_limit = $params['gap-limit'];
        $include_unused = $params['include-unused'];
        $network = Bitcoin::getNetwork();
        $gen_only = @$params['gen-only'];
                
        list($relpath_base, $abspath_base) = $this->get_derivation_paths( $params['derivation'] );

        $types = self::addrtypes();
        $apis = $params['api'] == 'roundrobin' ? blockchain_api_factory::instance_all() :
                                               [ blockchain_api_factory::instance( $params['api'] ) ];
        
        $tmap = array( 'receive' => self::receive_idx,
                       'change' => self::change_idx );
        
        foreach( range(self::receive_idx,self::change_idx) as $type) {
            
            if( $params['type'] != 'both' && $tmap[$params['type']] != $type ) {
                continue;
            }
        
            $gap = 0;  // reset gap!
            $batchnum = 1;
            while( 1 ) {
                $api = next( $apis ) ?: reset( $apis );
                
                $batch = [];
                $typename = $types[$type];
                $msg = sprintf( "-- %s Addresses.  Start of batch %s. --", $typename, $batchnum);
                mylogger()->log($msg, mylogger::info);

                // Goal: reduce number of API calls and increase performance.
                // if the api supports multiaddr lookups then we set batchsize to double the gap limit or max supported by api.
                //    note: doubling is disabled until secp256kp1 extension passes all test cases.
                //          because key generation is so slooooow without it.
                // otherwise, set it to 1 to minimize total API calls.
                // warning:  if secp256k1 extension not installed, addr generation will be slowest factor.
                // todo: check if extension installed, adjust batch size for multiaddr.
                $maxbatch = $api->max_batch_size();
                if( $params['batch-size'] == 'auto' ) {
                    $batchsize = $api->service_supports_multiaddr() ? $gap_limit * 2: 1;
                    $batchsize = $batchsize > $maxbatch ? $maxbatch : $batchsize;
                    if( $params['api'] == 'roundrobin' ) {
                        $batchsize = 1;
                    }
                }
                else {
                    $batchsize = $params['batch-size'];
                
                    // ensure batchsize is not greater than api max batch.
                    if( $batchsize > $maxbatch ) {
                        mylogger()->log( sprintf("Requested batch size %d exceeds API max. reducing to %d", $batchsize, $maxbatch), mylogger::warning );
                        $batchsize = $maxbatch;
                    }
                }
                
                // gen-only mode uses a single batch.
                if( $gen_only ) {
                    $batchsize = $gen_only;
                }
                
                $end = $batchnum * $batchsize;
                $start = $end - ($batchsize -1);
        
                mylogger()->log( "Generating $typename public keys", mylogger::info );
                for( $i = $start-1; $i < $end; $i ++ ) {
                    if( $i && $i % 5 == 0 ) {
                        mylogger()->log( "Generated $i public keys ($typename)", mylogger::info );
                    }
                    $relpath = $relpath_base . (strlen($relpath_base) ? '/' : '') . "$type/$i";
                    $abspath = strlen($abspath_base) ? $abspath_base . "/$type/$i" : '';
                    $key = $master->derivePath($relpath);
                    
                    // fixme: hack for copay/multisig.  maybe should use a callback?
                    if(!$is_multi) {
                        // bip32 path
                        $address = $this->address($key, $network);
                        $xpub = $this->toExtendedKey($key->withoutPrivateKey(), $network, $key_type);
                    }
                    else {
                        // copay/multisig path
                        $address = $this->address($key, $network);
                        $xpubs = array();
                        foreach($key->getKeys() as $key) {
                            $xpubs[] = $this->toExtendedKey($key->withoutPrivateKey(), $network, $key_type);
                        }
                        // note: encoding multiple xpub as csv string in same field.
                        $xpub = implode(',', $xpubs);
                    }
                    $batch[$address] = array( 'relpath' => $relpath,
                                              'abspath' => $abspath,
                                              'xpub' => $xpub,
                                              'index' => $i );
                }

                if( $gen_only ) {
                    foreach( $batch as $addr => $batchinfo ) {
                        $r = ['addr' => $addr,
                              'used' => null,
                              'status' => null,
                              'total_received' => null,
                              'total_sent' => null,
                              'balance' => null,
                              'type' => $typename,
                              'relpath' => $batchinfo['relpath'],
                              'abspath' => $batchinfo['abspath'],
                              'xpub' => $batchinfo['xpub']
                             ];
                        $addrs[] = $r;
                    }
                    break;
                }
                else {
                    mylogger()->log( sprintf( "Querying addresses %s to %s...", $start, $end), mylogger::info );
                    $response = $api->get_addresses_info( array_keys($batch), $params );
    
                    foreach( $response as $r ) {
                        if( $r['total_received'] == 0 ) {
                            $gap ++;
                            if( $gap > $gap_limit ) {
                                break 2;
                            }
                        }
                        else {
                            $gap = 0;
                        }
                        $r['type'] = $typename;
                        $batchinfo = $batch[$r['addr']];
                        $r['relpath'] = $batchinfo['relpath'];
                        $r['abspath'] = $batchinfo['abspath'];
                        $r['xpub'] = $batchinfo['xpub'];
                        $r['status'] = $r['used'] ? 'used' : 'unused';
                        if( $r['used'] || $include_unused ) {
                            $addrs[] = $r;
                        }
                    }
                }
        
                $batchnum ++;
            }
        }

        return $addrs;
    }
    
    private function address($key, $network) {
        $addrCreator = new AddressCreator();
        return $key->getAddress($addrCreator)->getAddress($network);
    }    

    /* Returns relative and absolution bip32 paths based on
     * type metadata ( bip number or app/wallet identifier )
     */
    protected function get_derivation_paths( $type ) {
        switch( $type ) {
            case 'bip32':
                $relpath_base = '';
                $abspath_base = 'm/0/0';
                break;
            case 'bip44':
                $relpath_base = '';
                $abspath_base = 'm/44/0/0';
                break;
            case 'bip45':
                $relpath_base = '';
                $abspath_base = 'm/45/0';
                break;
            case 'copaylegacy':
                // copay is crazy.
                $copay_constant = '2147483647';
                $relpath_base   = $copay_constant;
                $abspath_base   = 'm/45/' . $copay_constant;
                break;
            case 'relative':
                $relpath_base = '';
                $abspath_base = '';
                break;
            default:
                throw new exception( "Unknown derivation type: $type");
        }
        return array( $relpath_base, $abspath_base);
    }


    /* Returns all columns available for reports
     */
    static public function all_cols() {
        return ['addr', 'type', 'total_received', 'total_sent', 'balance', 'relpath', 'abspath', 'xpub', 'status'];
    }

    /* Returns default reporting columns
     */
    static public function default_cols() {
        return ['addr', 'type', 'total_received', 'total_sent', 'balance', 'relpath', 'status'];
    }

    static public function default_cols_gen_only() {
        return ['addr', 'type', 'relpath'];
    }

    
    /**
     * Implements a heuristic to differentiate bip44 from bip32 based on
     * key depth.  $xpub should be the top-most public key exported from a wallet.
     * For background, see:
     *   https://bitcointalk.org/index.php?topic=1000544.msg13378049#msg13378049
     */
    
    /* not used since we added the --derivation flag.
     * also, not reliable.
    static protected function bipnum_from_xpub($xpub) {
        $depth = $xpub->getDepth();
        switch( $depth ) {
            case 3: return 44;
            case 2: return 32;
        }
        return null;
    }

    static protected function depth_from_xpub($xpub) {
        switch( self::bipnum_from_xpub( $xpub ) ) {
            case 44: return 3;
            case 32: return 2;
        }
        return 0;
    }
    */
    
}

/* A class that generates wallet-discovery reports in various formats.
 */
class walletaddrsreport {

    /* prints out single report in one of several possible formats,
     * or multiple reports, one for each possible format.
     */
    static public function print_results( $params, $results ) {
        $format = $params['format'];
        $outfile = @$params['outfile'];
        
        $summary = self::result_count_by_type( $results );
        $summary['gen-only'] = $params['gen-only'];

        // remove columns not in report and change column order.
        $report_cols = $params['cols'];
        foreach( $results as &$r ) {
            $tmp = $r;
            $r = [];
            foreach( $report_cols as $colname ) {
                $r[$colname] = $tmp[$colname];
            }
        }

        if( $outfile && $format == 'all' ) {
            $formats = array( 'txt', 'csv', 'json', 'jsonpretty', 'html', 'addrlist' );
            
            foreach( $formats as $format ) {
                
                $outfile = sprintf( '%s/%s.%s',
                                    pathinfo($outfile, PATHINFO_DIRNAME),
                                    pathinfo($outfile, PATHINFO_FILENAME),
                                    $format );
                
                self::print_results_worker( $summary, $results, $outfile, $format );
            }
        }
        else {
            self::print_results_worker( $summary, $results, $outfile, $format );
        }
    }

    /* prints out single report in specified format, either to stdout or file.
     */
    static protected function print_results_worker( $summary, $results, $outfile, $format ) {

        $fname = $outfile ?: 'php://stdout';
        $fh = fopen( $fname, 'w' );

        switch( $format ) {
            case 'txt':        self::write_results_fixed_width( $fh, $results, $summary ); break;
            case 'addrlist':   self::write_results_addrlist( $fh, $results, $summary );    break;
            case 'csv':        self::write_results_csv( $fh, $results );         break;
            case 'json':       self::write_results_json( $fh, $results );        break;
            case 'html':       self::write_results_html( $fh, $results );        break;
            case 'jsonpretty': self::write_results_jsonpretty( $fh, $results );  break;
        }

        fclose( $fh );

        if( $outfile ) {
            mylogger()->log( "Report was written to $fname", mylogger::specialinfo );
        }
    }

    /* writes out results in json (raw) format
     */
    static public function write_results_json( $fh, $results ) {
        fwrite( $fh, json_encode( $results ) );
    }

    /* writes out results in jsonpretty format
     */
    static public function write_results_jsonpretty( $fh, $results ) {
        fwrite( $fh, json_encode( $results,  JSON_PRETTY_PRINT ) );
    }
    
    /* writes out results in csv format
     */
    static public function write_results_csv( $fh, $results ) {
        if( @$results[0] ) {
            fputcsv( $fh, array_keys( $results[0] ) );
        }
        
        foreach( $results as $row ) {
            fputcsv( $fh, $row );
        }
    }

    /* writes out results in html format
     */
    static public function write_results_html( $fh, $results ) {
        $html = '';
        $data = [];

        // make our own array to avoid modifying the original.
        foreach( $results as $row ) {
            $myrow = $row;
            if( isset( $myrow['addr'] ) ) {
                $addr_url = sprintf( 'http://blockchain.info/address/%s', $myrow['addr'] );
                $myrow['addr'] = sprintf( '<a href="%s">%s</a>', $addr_url, $myrow['addr'] );
            }
            $data[] = $myrow;
        }

        if( @$data[0] ) {
            $header = array_keys( $data[0] );
        }
        else {
           // bail.
           return $html;
        }
    
        $table = new html_table();
        $table->header_attrs = array();
        $table->table_attrs = array( 'class' => 'walletaddrs bordered' );
        $html .= $table->table_with_header( $data, $header );
            
        fwrite( $fh, $html );
    }
    
    /* writes out results as a plain text table.  similar to mysql console results.
     */
    static protected function write_results_fixed_width( $fh, $results, $summary ) {

        fwrite( $fh, " --- Wallet Discovery Report --- \n\n" );
        
        if( !$summary['gen-only'] ) {
            fprintf($fh, "Found %s Receive addresses and %s Change addresses.\n" .
                         "  Receive --  Used: %s\tUnused: %s\n" .
                         "  Change  --  Used: %s\tUnused: %s\n\n",
                         $summary['num_receive'], $summary['num_change'],
                         $summary['num_receive_used'], $summary['num_receive_unused'],
                         $summary['num_change_used'], $summary['num_change_unused']
                    );
        }
        
        $buf = mysqlutil::format_results_fixed_width( $results );
        fwrite( $fh, $buf );
        
        fwrite( $fh, "\n" );
    }
    
    /* writes out results as a plain text list of addresses. single column only.
     */
    static protected function write_results_addrlist( $fh, $results, $summary ) {

        fwrite( $fh, " --- Wallet Discovery Report --- \n\n" );
        
        if( !$summary['gen-only'] ) {
            fprintf($fh, "Found %s Receive addresses and %s Change addresses.\n" .
                         "  Receive --  Used: %s\tUnused: %s\n" .
                         "  Change  --  Used: %s\tUnused: %s\n\n",
                         $summary['num_receive'], $summary['num_change'],
                         $summary['num_receive_used'], $summary['num_receive_unused'],
                         $summary['num_change_used'], $summary['num_change_unused']
                    );
        }
        
        foreach( $results as $info ) {
            if( @$info['addr'] ) {
                fprintf( $fh, "%s\n", $info['addr'] );
            }
        }
        
        fwrite( $fh, "\n" );
    }
    
    /* returns count of results by address type
     */
    static public function result_count_by_type($results) {
        $num_receive = $num_change = $num_receive_used = $num_change_used = 0;
        foreach( $results as $r) {
            switch( strtolower($r['type']) ) {
                case 'receive':
                    $num_receive ++;
                    if( $r['used'] ) {
                        $num_receive_used ++;
                    }
                    break;
                case 'change':
                    $num_change ++;
                    if( $r['used'] ) {
                        $num_change_used ++;
                    }
                    break;
                default: throw new Exception("Invalid address type: " . $r['type'] );
            }
        }
        return ['num_receive' => $num_receive,
                'num_change'  => $num_change,
                'num_receive_used' => $num_receive_used,
                'num_change_used' => $num_change_used,
                'num_receive_unused' => $num_receive - $num_receive_used,
                'num_change_unused' => $num_change - $num_change_used,
                ];
    }
}
