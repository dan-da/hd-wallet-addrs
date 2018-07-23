<?php

class insight extends test_base {

    public function runtests() {
        $this->test1();
    }
    
    protected function test1() {
        // wallet unknown.
        // obtained from https://blockchain.info/xpub/xpub6CUGRUonZSQ4TWtTMmzXdrXDtypWKiKrhko4egpiMZbpiaQL2jkwSB1icqYh2cfDfVxdx4df189oLKnC5fSwqPfgyP3hooxujYzAu3fDVmz
        $xpub = 'xpub6CUGRUonZSQ4TWtTMmzXdrXDtypWKiKrhko4egpiMZbpiaQL2jkwSB1icqYh2cfDfVxdx4df189oLKnC5fSwqPfgyP3hooxujYzAu3fDVmz';
        $args = "-g --api=insight --gap-limit=2  --xpub=$xpub";
        $data = hdwalletaddrscmd::runjson( $args );
        
        $col = 'Number of addresses found.';
        $this->eq( count($data), 7, $col );

        $firstrow = @$data[0];

        $col = 'Address 1';
        $this->eq( @$data[0]['addr'], '1EfgV2Hr5CDjXPavHDpDMjmU33BA2veHy6', $col );

        $col = 'Address 2';
        $this->eq( @$data[1]['addr'], '12iNxzdF6KFZ14UyRTYCRuptxkKSSVHzqF', $col );
        
        $col = 'Type';
        $this->eq( @$firstrow['type'], 'Receive', $col );
        
        $col = 'Total received';
        $this->eq( @$firstrow['total_received'], '0.00100000', $col );
        
        $col = 'Total sent';
        $this->eq( @$firstrow['total_sent'], '0.00100000', $col );
        
        $col = 'balance';
        $this->eq( @$firstrow['balance'], '0.00000000', $col );

        $col = 'relpath';
        $this->eq( @$firstrow['relpath'], '0/0', $col );
        
        $col = 'abspath';
        $this->eq( @$firstrow['abspath'], null, $col );
    }

}
