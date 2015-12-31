<?php

class copaylegacy extends test_base {

    public function runtests() {
        $this->test1of1p2sh();
        $this->test2of2();
    }
    
    public function test1of1p2sh() {
        // from copay 1.1.x.  Bip 45.  1 of 1, p2sh.  ( addresses start with 3 )
        $xpub = 'xpub697odnriKgTgWE4my6au8nd8haUfAMzLGFpDemAkRbCMgGVxANuj9DffNLgDjPA1dnxzi8oFmM79ZPgKVfCV7Saj8sQUL7tJfeZDuyQNGDm';
        $args = "-g --gap-limit=2  --xpub=$xpub --derivation=copaylegacy --include-unused";

        $data = hdwalletaddrscmd::runjson( $args );
        
        $col = 'Number of addresses found.';
        $this->eq( count($data), 4, $col );   // 2 empty receive + 2 empty change
        
        $col = 'First Receive Address';
        $this->eq( @$data[0]['addr'], '3LHgjejeCnQEhLGpmc1q4RmPXypKhjbgpY', $col );

        $col = 'Second Receive Address';
        $this->eq( @$data[1]['addr'], '3Jdd25xHSCDFrMeCoW62963vf22UoKBmtP', $col );

        $col = 'First Change Address';
        $this->eq( @$data[2]['addr'], '3JZ3YR6sgyqq6xcGtpcAvYBCX7gM9cPU3c', $col );

        $col = 'Second Change Address';
        $this->eq( @$data[3]['addr'], '32KNwkcQzBHYejvnJpWDwUWMbHGZd4Q6fH', $col );
    }
    
    protected function test2of2() {
        // from copay 1.x test wallet 2 of 2
        $keys = array( 'xpub68bjYyPhqAwK4T8WtXuGvruSQoJu1vdLD7DYc591MkFCR7wD9gyzteFYmzRyytWJ2SzTqZNTgggvPEyqEy9oArjLF7xhte5js1Lp1EPipwJ',
                       'xpub68ufoGjY41tQqP4LpeyYornuNxm8DNy2Rn7KAPUTAwFouj821eqcVpWw1jonrm2Xg5jnnSrd1QPQzGve3f66ZLf6Ni9VY6aN3AjYa4e7XTE' );
        $numsig=2;  // 2 signers.
        $xpub = implode(',', $keys );
        $args = "-g --gap-limit=2  --xpub=$xpub --derivation=copaylegacy --numsig=$numsig --include-unused";

        $data = hdwalletaddrscmd::runjson( $args );
        
        $col = 'Number of addresses found.';
        $this->eq( count($data), 4, $col );   // 2 empty receive + 2 empty change
        
        $col = 'First Receive Address';
        $this->eq( @$data[0]['addr'], '35uhrWpDTj3Y7EwR9AWjACGfT47txtpH1v', $col );

        $col = 'Second Receive Address';
        $this->eq( @$data[1]['addr'], '3BnXxkW9CVCLn1EboGDJ8434eKFWZGHsjn', $col );

        $col = 'First Change Address';
        $this->eq( @$data[2]['addr'], '38dzdCQXatNdT9nWG7thpGC9KjBVLphZRP', $col );

        $col = 'Second Change Address';
        $this->eq( @$data[3]['addr'], '3CfbgQ5BxWRFBYXJxEVAmVCZsatdJfc2rS', $col );
    }
}
