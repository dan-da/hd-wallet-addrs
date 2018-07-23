<?php

class blockonomics extends test_base {

    public function runtests() {
        $this->test1();
    }
    
    protected function test1() {
        // Verify at https://www.blockonomics.co/#/search?q=xpub6BfKpqjTwvH21wJGWEfxLppb8sU7C6FJge2kWb9315oP4ZVqCXG29cdUtkyu7YQhHyfA5nt63nzcNZHYmqXYHDxYo8mm1Xq1dAC7YtodwUR
        $xpub = 'xpub6BfKpqjTwvH21wJGWEfxLppb8sU7C6FJge2kWb9315oP4ZVqCXG29cdUtkyu7YQhHyfA5nt63nzcNZHYmqXYHDxYo8mm1Xq1dAC7YtodwUR';
        $args = "-g --gap-limit=5  --xpub=$xpub";
        $data = hdwalletaddrscmd::runjson( $args );
        
        $col = 'Number of addresses found.';
        $this->eq( count($data), 7, $col );
        
        // note: for some reason blockonomics is using a different sort order
        //       than we do.

        $col = 'Address 1';
        $this->eq( @$data[0]['addr'], '1Ge6rDuyCdYVGhXZjcK4251q67GXMKx6xK', $col );

        $col = 'Address 2';
        $this->eq( @$data[1]['addr'], '1NVsB73WmDGXSxv77sh9PZENH2x3RRnkDY', $col );

        $col = 'Address 3';
        $this->eq( @$data[2]['addr'], '1BkgqiHcvfnQ2wrPN5D2ycrvZas3nibMjC', $col );

        $col = 'Address 4';
        $this->eq( @$data[3]['addr'], '15qkqdGFvBBvd8MHnRM3hhXkfTtEeP4mGP', $col );

        $col = 'Address 5';
        $this->eq( @$data[4]['addr'], '12SisoiXLUEbkytL5Pzia1jBY8gJP5XN8D', $col );
    }
}
