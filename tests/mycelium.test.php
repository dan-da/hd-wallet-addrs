<?php

class mycelium extends test_base {

    public function runtests() {
        $this->test_derivation();
    }
    
    protected function test_derivation() {
        // from mycelium android
        $xpub = 'xpub6DJTvbWn3dbKoVLJScvgPNUwEsGu19nMUXaRaHbwmQdY6wcJZgNd1iqfmfg5a1M3ckupB4roptN73nPqeU7MV5EdJS6ZdwUQ4nixjAuz6oM';
        $args = "-g --include-unused --gap-limit=2  --xpub=$xpub";
        $data = hdwalletaddrscmd::runjson( $args );
        
        $col = 'Number of addresses found.';
        $this->eq( count($data), 11, $col );   // 2 empty receive + 2 empty change

        $col = 'First Receive Address';
        $this->eq( @$data[0]['addr'], '137vYfvKUwLnht5217E5vQ3zCp63Fp194A', $col );

        $col = 'Second Receive Address';
        $this->eq( @$data[1]['addr'], '1JYd1RBcL71hvhJxVAZrDiE2YTNMw9sQVm', $col );

        $col = 'First Change Address';
        $this->eq( @$data[2]['addr'], '1DKkMD6qskkkkJcHpJZsJtjq61sbFVsvv5', $col );

        $col = 'Second Change Address';
        $this->eq( @$data[3]['addr'], '19bEUR8a8xynRhPxUi5TAMzQRNF5jaPidS', $col );

    }

}
