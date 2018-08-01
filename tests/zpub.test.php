<?php

class zpub extends test_base {

    public function runtests() {
        $this->test_derivation();
    }
    
    protected function test_derivation() {
        $xpub = 'zprvAeLVjPKxjMgvb9essMXfV7puSH5Qv1zD52iUvcmKN1CqfoqXZLYWhQkLyL3ZkhvSR1KZrhvsmyr2cM2e7hSBVzDq6Zydf6vG5iEoTPzWGCy';
        $args = "-g --gen-only=1 --xpub=$xpub";
        $data = hdwalletaddrscmd::runjson( $args );
        
        $col = 'Receive Addr 1';
        $this->eq( @$data[0]['addr'], 'bc1qc9wm8km3g375pqlcqwqd4hneaxrd8ll26t9zrc', $col );

        $col = 'Change Addr 1';
        $this->eq( @$data[1]['addr'], 'bc1qykelrp6sy32hj7cneyyu0vsq7qsfl00kvc0vxl', $col );
    }

}
