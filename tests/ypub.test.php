<?php

class ypub extends test_base {

    public function runtests() {
        $this->test_derivation();
    }
    
    protected function test_derivation() {
        $xpub = 'yprvALNfpZb4ya9ug4QgV7Xrf1ShvFCTKTNvHT1ik88pDC7WjZDw6sWk6qKFwWgkjoGiBDHbALMY21DhFmGjSuHFQrNANUmKuNmjj2yeFvnTw88';
        $args = "-g --gen-only=1 --xpub=$xpub";
        $data = hdwalletaddrscmd::runjson( $args );
        
        $col = 'Receive Addr 1';
        $this->eq( @$data[0]['addr'], '3J2uGxSgXWfZm3V3nHgFJY4AFVUnHbWUeW', $col );

        $col = 'Change Addr 1';
        $this->eq( @$data[1]['addr'], '3GArgEXwqcoDd8engKqHQuex5EK5zyKkmw', $col );
    }

}
