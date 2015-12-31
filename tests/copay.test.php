<?php

class copay extends test_base {

    public function runtests() {
        $this->test1of1();
        $this->test2of3();
    }

    public function test1of1() {
        // from copay 1.6.3.  Bip 44.  1 of 1, p2pbk.  ( addresses start with 1 )
        $xpub = 'xpub6DRYJtFQz8tn96eYRpiXSNU6RXxSXJfKPqBEx2BgcQAo8Nd7f8awszFccEosLeFAxBdUc18uCJ3rmkAVFoPrz4EYJreoyYrDyqsRm1oNBrX';
        $args = "-g --gap-limit=2  --xpub=$xpub --include-unused";

        $data = hdwalletaddrscmd::runjson( $args );

        $col = 'Number of addresses found.';
        $this->eq( count($data), 4, $col );   // 2 empty receive + 2 empty change

        $col = 'First Receive Address';
        $this->eq( @$data[0]['addr'], '18YN94BXLGriw9UDDJrYMjfvUXS92x3Da1', $col );

        $col = 'Second Receive Address';
        $this->eq( @$data[1]['addr'], '15qHhha3zhz61gfAAJDL6vSqsr8ZcFv8aA', $col );

        $col = 'First Change Address';
        $this->eq( @$data[2]['addr'], '1KjNauo2LAWXG5iS8cqdDmGcCFt8QqpEh5', $col );

        $col = 'Second Change Address';
        $this->eq( @$data[3]['addr'], '186VLsD7EPVCwCtiJZ45FzFBZ3unigHC9S', $col );
    }
    
    protected function test2of3() {
        // from copay 1.6.3 test wallet 2 of 3
        // note: wallet uses bip44 derivation strategy.
        $keys = array( 'xpub6CZte6DfeMoVwxv3ShiMwQjET47nRENqrkZaSXTcP7Yaja6sxyRbiyqPD7kfy4W2dTTuTdV4jHMmSe1k1qteTMN7qDLndt1RfQ8RLM3pjzb',
                       'xpub6DUGj5hRwp7t3DoH554Ce7p3KLepccYfG5BVbvyPSArTepacc3aPRDTMz3GSdoX1HgVYKBSaR6fFDm1daEtSQFBSNTq4X93pd8dBFyPW2gz',
                       'xpub6DRFPDtHueJ5sfqzcLSyoKL6TQZMofvjpLzsVXsWqjgYuAtUtdU8YjWFvpa2xegWLFeLQ38KLJzWdKQ3CsAQQLoMYnBsQy3FCeTDuxgcsfK',
                       );
        $numsig=2;  // 2 signers.
        $xpub = implode(',', $keys );
        $args = "-g --gap-limit=2  --xpub=$xpub --numsig=$numsig --include-unused";

        $data = hdwalletaddrscmd::runjson( $args );
        
        $col = 'Number of addresses found.';
        $this->eq( count($data), 4, $col );   // 2 empty receive + 2 empty change
        
        $col = 'First Receive Address';
        $this->eq( @$data[0]['addr'], '339H3pYP9AKiEo74D1BWiSK8jhWXsrJ3yk', $col );

        $col = 'Second Receive Address';
        $this->eq( @$data[1]['addr'], '3NcBBWtDscKchgkUCY3eEQZgYh8STtcona', $col );

        $col = 'First Change Address';
        $this->eq( @$data[2]['addr'], '3QtjkbY8Km4v5KCgTZxD7VW2vPCsBqkV3V', $col );

        $col = 'Second Change Address';
        $this->eq( @$data[3]['addr'], '3B7xNx7dCT6ydcVF1xQpEtG8UFeeh2PyAk', $col );
    }
}
