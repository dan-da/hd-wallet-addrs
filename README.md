# hd-wallet-addrs

A command-line  tool for finding bitcoin hd-wallet addresses that have received funds.

This tool does two primary things:

1. derive hd-wallet addresses (both change and receive) according to bip32 rules.
2. examines the blockchain to find the addresses that have actually been used.  (received funds at least once)

A web frontend for this tool is available at:
https://mybitprices.info/hd-wallet-addrs.html

Both regular HD wallets (single address) and multi-sig wallets (eg Copay) are
supported.

Segwit addresses are generated if a ypub or zpub key is provided.
(ypub: segwit-p2sh, zpub: bech32)

Reports are available in json, plaintext, and html.  Columns can be
changed or re-ordered via command-line.

hd-wallet-addrs is general purpose for anyone needing to discover which addresses
are actually used in their wallet, including change addresses.

The motivation for building this tool was to simplify extracting used wallet
addresses for accounting purposes.  In particular for use with:
* <a href="http://github.com/dan-da/bitprices">bitprices</a> - a command line utility for wallet pricing history and cost-based accounting.
* <a href="http://mybitprices.info">mybitprices.info</a> - an easy-to-use web frontend to bitprices.

See also: [hd-wallet-derive](https://github.com/dan-da/hd-wallet-derive) -- a tool that derives bip32 addresses and private keys.

# Let's see some examples.

```
$ ./hd-wallet-addrs.php -g --xpub=xpub6BfKpqjTwvH21wJGWEfxLppb8sU7C6FJge2kWb9315oP4ZVqCXG29cdUtkyu7YQhHyfA5nt63nzcNZHYmqXYHDxYo8mm1Xq1dAC7YtodwUR --logfile=/tmp/log.txt

 --- Wallet Discovery Report --- 

Found 3 Receive addresses and 2 Change addresses.
  Receive --  Used: 3   Unused: 0
  Change  --  Used: 2   Unused: 0

+------------------------------------+---------+----------------+------------+------------+---------+
| addr                               | type    | total_received | total_sent | balance    | relpath |
+------------------------------------+---------+----------------+------------+------------+---------+
| 1Ge6rDuyCdYVGhXZjcK4251q67GXMKx6xK | Receive |     0.00120000 | 0.00100000 | 0.00020000 | 0/0     |
| 1NVsB73WmDGXSxv77sh9PZENH2x3RRnkDY | Receive |     0.00130000 | 0.00100000 | 0.00030000 | 0/1     |
| 1BkgqiHcvfnQ2wrPN5D2ycrvZas3nibMjC | Receive |     0.00040000 | 0.00000000 | 0.00040000 | 0/2     |
| 12SisoiXLUEbkytL5Pzia1jBY8gJP5XN8D | Change  |     0.00184874 | 0.00000000 | 0.00184874 | 1/0     |
| 1CkvACVpFwkPnMG13w9kXXE9YcsiyL4pcY | Change  |     0.00194876 | 0.00000000 | 0.00194876 | 1/1     |
+------------------------------------+---------+----------------+------------+------------+---------+
```

We can change up the fields and specify to use bip44 derivation to generate an absolute path.

Tip: The abspath column is empty when --derivation=relative, which is the default.

```
$ ./hd-wallet-addrs.php -g --xpub=xpub6BfKpqjTwvH21wJGWEfxLppb8sU7C6FJge2kWb9315oP4ZVqCXG29cdUtkyu7YQhHyfA5nt63nzcNZHYmqXYHDxYo8mm1Xq1dAC7YtodwUR --cols=type,abspath,relpath,addr --derivation=bip44 --logfile=/tmp/log.txt

 --- Wallet Discovery Report --- 

Found 3 Receive addresses and 2 Change addresses.
  Receive --  Used: 3   Unused: 0
  Change  --  Used: 2   Unused: 0

+---------+--------------+---------+------------------------------------+
| type    | abspath      | relpath | addr                               |
+---------+--------------+---------+------------------------------------+
| Receive | m/44/0/0/0/0 | 0/0     | 1Ge6rDuyCdYVGhXZjcK4251q67GXMKx6xK |
| Receive | m/44/0/0/0/1 | 0/1     | 1NVsB73WmDGXSxv77sh9PZENH2x3RRnkDY |
| Receive | m/44/0/0/0/2 | 0/2     | 1BkgqiHcvfnQ2wrPN5D2ycrvZas3nibMjC |
| Change  | m/44/0/0/1/0 | 1/0     | 12SisoiXLUEbkytL5Pzia1jBY8gJP5XN8D |
| Change  | m/44/0/0/1/1 | 1/1     | 1CkvACVpFwkPnMG13w9kXXE9YcsiyL4pcY |
+---------+--------------+---------+------------------------------------+
```

Or get a list for easy copy/paste.

```
$ ./hd-wallet-addrs.php -g --xpub=xpub6BfKpqjTwvH21wJGWEfxLppb8sU7C6FJge2kWb9315oP4ZVqCXG29cdUtkyu7YQhHyfA5nt63nzcNZHYmqXYHDxYo8mm1Xq1dAC7YtodwUR --format=addrlist --logfile=/tmp/log.txt

 --- Wallet Discovery Report --- 

Found 3 Receive addresses and 2 Change addresses.
  Receive --  Used: 3   Unused: 0
  Change  --  Used: 2   Unused: 0

1Ge6rDuyCdYVGhXZjcK4251q67GXMKx6xK
1NVsB73WmDGXSxv77sh9PZENH2x3RRnkDY
1BkgqiHcvfnQ2wrPN5D2ycrvZas3nibMjC
12SisoiXLUEbkytL5Pzia1jBY8gJP5XN8D
1CkvACVpFwkPnMG13w9kXXE9YcsiyL4pcY
```

Or JSON

```
./hd-wallet-addrs.php -g --xpub=xpub6BfKpqjTwvH21wJGWEfxLppb8sU7C6FJge2kWb9315oP4ZVqCXG29cdUtkyu7YQhHyfA5nt63nzcNZHYmqXYHDxYo8mm1Xq1dAC7YtodwUR --cols=type,abspath,relpath,addr --format=jsonpretty  --derivation=bip44 --logfile=/tmp/log.txt
[
    {
        "type": "Receive",
        "abspath": "m\/44\/0\/0\/0\/0",
        "relpath": "0\/0",
        "addr": "1Ge6rDuyCdYVGhXZjcK4251q67GXMKx6xK"
    },
    ...
]
```

Or CSV

```
./hd-wallet-addrs.php -g --xpub=xpub6BfKpqjTwvH21wJGWEfxLppb8sU7C6FJge2kWb9315oP4ZVqCXG29cdUtkyu7YQhHyfA5nt63nzcNZHYmqXYHDxYo8mm1Xq1dAC7YtodwUR --cols=type,abspath,relpath,addr --format=csv  --derivation=bip44 --logfile=/tmp/log.txt
type,abspath,relpath,addr
Receive,m/44/0/0/0/0,0/0,1Ge6rDuyCdYVGhXZjcK4251q67GXMKx6xK
Receive,m/44/0/0/0/1,0/1,1NVsB73WmDGXSxv77sh9PZENH2x3RRnkDY
Receive,m/44/0/0/0/2,0/2,1BkgqiHcvfnQ2wrPN5D2ycrvZas3nibMjC
Change,m/44/0/0/1/0,1/0,12SisoiXLUEbkytL5Pzia1jBY8gJP5XN8D
Change,m/44/0/0/1/1,1/1,1CkvACVpFwkPnMG13w9kXXE9YcsiyL4pcY
```

# multi-sig examples.

So far multi-sig has been tested with copay (bip44 and bip45) only.
Older versions of Copay using bip45 require the --derivation=copaylegacy flag.

multisig requires multiple xpub keys and use of the --numsig flag to indicate
the required number of signers.  (m of n)

## discovering an empty Copay 1.6.3+ (bip44) 2 of 3 wallet.

This test wallet has no funds, so we use --include-unused to obtain the initial addresses up to the
gap limit.  The gap limit default is 20, but we use 2 here for brevity.
```
$ ./hd-wallet-addrs.php -g --numsig=2 --gap-limit=2  --xpub=xpub6CZte6DfeMoVwxv3ShiMwQjET47nRENqrkZaSXTcP7Yaja6sxyRbiyqPD7kfy4W2dTTuTdV4jHMmSe1k1qteTMN7qDLndt1RfQ8RLM3pjzb,xpub6DUGj5hRwp7t3DoH554Ce7p3KLepccYfG5BVbvyPSArTepacc3aPRDTMz3GSdoX1HgVYKBSaR6fFDm1daEtSQFBSNTq4X93pd8dBFyPW2gz,xpub6DRFPDtHueJ5sfqzcLSyoKL6TQZMofvjpLzsVXsWqjgYuAtUtdU8YjWFvpa2xegWLFeLQ38KLJzWdKQ3CsAQQLoMYnBsQy3FCeTDuxgcsfK --include-unused --logfile=/tmp/out.txt

 --- Wallet Discovery Report --- 

Found 2 Receive addresses and 2 Change addresses.
  Receive --  Used: 0   Unused: 2
  Change  --  Used: 0   Unused: 2

+------------------------------------+---------+----------------+------------+------------+---------+
| addr                               | type    | total_received | total_sent | balance    | relpath |
+------------------------------------+---------+----------------+------------+------------+---------+
| 339H3pYP9AKiEo74D1BWiSK8jhWXsrJ3yk | Receive |     0.00000000 | 0.00000000 | 0.00000000 | 0/0     |
| 3NcBBWtDscKchgkUCY3eEQZgYh8STtcona | Receive |     0.00000000 | 0.00000000 | 0.00000000 | 0/1     |
| 3QtjkbY8Km4v5KCgTZxD7VW2vPCsBqkV3V | Change  |     0.00000000 | 0.00000000 | 0.00000000 | 1/0     |
| 3B7xNx7dCT6ydcVF1xQpEtG8UFeeh2PyAk | Change  |     0.00000000 | 0.00000000 | 0.00000000 | 1/1     |
+------------------------------------+---------+----------------+------------+------------+---------+
```

## discovering an empty Copay 1.1.x (bip45) 1 of 1 wallet.

Legacy versions of Copay used bip45 in a special way that the tool cannot detect without help.

Note the use of --derivation=copaylegacy

(Copay 1.6.3+ 1 of 1 wallets use bip44 derivation and do not require any special arguments.)


```
$ ./hd-wallet-addrs.php -g --derivation=copaylegacy --gap-limit=2  --xpub=xpub697odnriKgTgWE4my6au8nd8haUfAMzLGFpDemAkRbCMgGVxANuj9DffNLgDjPA1dnxzi8oFmM79ZPgKVfCV7Saj8sQUL7tJfeZDuyQNGDm --include-unused --logfile=/tmp/out.txt

 --- Wallet Discovery Report --- 

Found 2 Receive addresses and 2 Change addresses.
  Receive --  Used: 0   Unused: 2
  Change  --  Used: 0   Unused: 2

+------------------------------------+---------+----------------+------------+------------+----------------+
| addr                               | type    | total_received | total_sent | balance    | relpath        |
+------------------------------------+---------+----------------+------------+------------+----------------+
| 3LHgjejeCnQEhLGpmc1q4RmPXypKhjbgpY | Receive |     0.00000000 | 0.00000000 | 0.00000000 | 2147483647/0/0 |
| 3Jdd25xHSCDFrMeCoW62963vf22UoKBmtP | Receive |     0.00000000 | 0.00000000 | 0.00000000 | 2147483647/0/1 |
| 3JZ3YR6sgyqq6xcGtpcAvYBCX7gM9cPU3c | Change  |     0.00000000 | 0.00000000 | 0.00000000 | 2147483647/1/0 |
| 32KNwkcQzBHYejvnJpWDwUWMbHGZd4Q6fH | Change  |     0.00000000 | 0.00000000 | 0.00000000 | 2147483647/1/1 |
+------------------------------------+---------+----------------+------------+------------+----------------+
```

### Warning for users of Copay 1.6.2 and below

Older Copay versions made it possible to generate gaps larger than 20. This is
because it would generate a new address each time the receive screen was viewed
and did not respect the standard gap-limit of 20.

Checking only 20 addresses could possibly leave you without discovering funds.
If you suspect this may be happening, a workaround is to specify a larger gap
limit such as 100 via the gap-limit argument.



## discovering an empty Copay 1.1.x (bip45) 2 of 2 wallet.

Again we must use --derivation=copaylegacy

```
$ ./hd-wallet-addrs.php --derivation=copaylegacy -g --gap-limit=2  --xpub=xpub68bjYyPhqAwK4T8WtXuGvruSQoJu1vdLD7DYc591MkFCR7wD9gyzteFYmzRyytWJ2SzTqZNTgggvPEyqEy9oArjLF7xhte5js1Lp1EPipwJ,xpub68ufoGjY41tQqP4LpeyYornuNxm8DNy2Rn7KAPUTAwFouj821eqcVpWw1jonrm2Xg5jnnSrd1QPQzGve3f66ZLf6Ni9VY6aN3AjYa4e7XTE --numsig=2 --include-unused --logfile=/tmp/out.txt

 --- Wallet Discovery Report --- 

Found 2 Receive addresses and 2 Change addresses.
  Receive --  Used: 0   Unused: 2
  Change  --  Used: 0   Unused: 2

+------------------------------------+---------+----------------+------------+------------+----------------+
| addr                               | type    | total_received | total_sent | balance    | relpath        |
+------------------------------------+---------+----------------+------------+------------+----------------+
| 35uhrWpDTj3Y7EwR9AWjACGfT47txtpH1v | Receive |     0.00000000 | 0.00000000 | 0.00000000 | 2147483647/0/0 |
| 3BnXxkW9CVCLn1EboGDJ8434eKFWZGHsjn | Receive |     0.00000000 | 0.00000000 | 0.00000000 | 2147483647/0/1 |
| 38dzdCQXatNdT9nWG7thpGC9KjBVLphZRP | Change  |     0.00000000 | 0.00000000 | 0.00000000 | 2147483647/1/0 |
| 3CfbgQ5BxWRFBYXJxEVAmVCZsatdJfc2rS | Change  |     0.00000000 | 0.00000000 | 0.00000000 | 2147483647/1/1 |
+------------------------------------+---------+----------------+------------+------------+----------------+
```

# How discovery works

In plain english, discovery works by mathematically deriving the addresses
for your wallet in order and checking if each one has been used or not.

A slightly more technical description of the process:
* starting from the extended public key (xpub)
* for receive addresses, then change addresses
  * derive batches of xpub child addresses (bip32: 0/*)
  * for each batch
    * check if each address has received funds  (API call to oracle/server)
    * until 20 (default) unused addresses in a row are found.

# Privacy implications

An important thing to recognize is that unless you are running a toshi or
insight server locally, the discovery process will send your public addresses
to a third party.  ie: BlockChain.info, BitPay (insight), or CoinBase (toshi)

The third party will have no way to spend your funds.

The third party could track your requests and guess/assume that your addresses
are associated with your IP, or are associated with eachother.

If that is something you care about, then you should investigate how to run
toshi or insight locally and use the --toshi or --insight flags to specify
the local server URL.

There is now a feature that helps to improve privacy when using third-party
API servers.  The **--api=roundrobin** flag will cycle through the available
blockchain providers and send individual addresses to each.  In this way, no
single provider will have access to all the queried wallet addresses.

Querying for individual addresses is slow.  The --batch-size flag may be used
to increase the number of addresses sent to each provider.  


# Use at your own risk.

The author makes no claims or guarantees of correctness.


# Output formats

The report may be printed in the following formats:
* plain  - an ascii formatted table, as above.  intended for humans.
* csv - CSV format.  For spreadsheet programs.
* json - raw json format.  for programs to read easily.
* jsonpretty - pretty json format.  for programs or humans.
* addrlist - single column address list. for easy cut/paste.

Additionally, the report may contain incoming transactions only, outgoing
transactions only, or both types.

# Usage

```
$ ./hd-wallet-addrs.php

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
    --include-unused     if present, unused addresses in gaps less than
                         gap limit will be included
    
    --gen-only=<n>      will generate n receive addresses and n change addresses
                          but will not query the blockchain to determine if they
                          have been used.
                          
    --type=<type>       receive|change|both.  default=both
    
    --api=<api>          toshi|insight|blockchaindotinfo|btcd|roundrobin
                           default = blockchaindotinfo  (fastest)
                           roundrobin will use a different API for each batch
                            to improve privacy.  It also sets --batch-size to
                            1 if set to auto.
                            
    --batch-size=<n>    integer|auto   default=auto.
                          The number of addresses to lookup in each batch.
    
    --cols=<cols>        a csv list of columns, or "all"
                         all:
                          (addr,type,total_received,total_sent,balance,relpath,abspath,xpub)
                         default:
                          (addr,type,total_received,total_sent,balance,relpath)

    --outfile=<path>     specify output file path.
    --format=<format>    txt|csv|json|jsonpretty|html|addrlist|all   default=txt
    
                         if all is specified then a file will be created
                         for each format with appropriate extension.
                         only works when outfile is specified.
                         
    --toshi=<url>       toshi server. defaults to https://bitcoin.toshi.io
    --insight=<url>     insight server. defaults to https://insight.bitpay.com/api
    
    --blockchaindotinfo=<url>
                        blockchain.info server.  defaults to https://blockchain.info
    
    --btcd=<url>        btcd rpc server.  specify as http://user:pass@host:port.  https ok also
                          btcd does not return balance or total sent/received.
    
    --oracle-raw=<p>    path to save raw server response, optional.
    --oracle-json=<p>   path to save formatted server response, optional.
    
    --logfile=<file>    path to logfile. if not present logs to stdout.
    --loglevel=<level>  debug,info,specialinfo,warning,exception,fatalerror
                          default = info
```


# Installation and Running.

PHP's gmp and mcrypt extensions are required.  Here's how to install on ubuntu.
```
 sudo apt-get install php5-gmp php5-mcrypt
```

Basics
```
 git clone https://github.com/dan-da/hd-wallet-addrs
 cd hd-wallet-addrs
 php -r "readfile('https://getcomposer.org/installer');" | php
 php composer.phar install
```

Try an example
```
./hd-wallet-addrs.php -g --xpub=xpub6BfKpqjTwvH21wJGWEfxLppb8sU7C6FJge2kWb9315oP4ZVqCXG29cdUtkyu7YQhHyfA5nt63nzcNZHYmqXYHDxYo8mm1Xq1dAC7YtodwUR
```

Or to hide log messages
```
./hd-wallet-addrs.php -g --xpub=xpub6BfKpqjTwvH21wJGWEfxLppb8sU7C6FJge2kWb9315oP4ZVqCXG29cdUtkyu7YQhHyfA5nt63nzcNZHYmqXYHDxYo8mm1Xq1dAC7YtodwUR --logfile=/tmp/log.txt
```

Run Test cases
```
 cd tests
 ./test_runner.php
```

It is really slow to generate keys in PHP.  For a huge speedup, you can install the
secp256k1 extension from:

<a href="https://github.com/Bit-Wasp/secp256k1-php">https://github.com/Bit-Wasp/secp256k1-php</a>

Versions of secp256k1-php after v0.0.7 require PHP7, so if you are using PHP5,
the install instructions on that page must be modified as follows:

```
$ cd secp256k1-php/secp256k1
$ git checkout v0.0.7
$ phpize && ./configure --with-secp256k1 && make && sudo make install
```

Note:  on some installations you may need to specify phpize5 instead of phpize.


# Blockchain API provider notes.

tip!  use the --api flag to switch between blockchain API providers.

Each API has strengths and weaknesses. Some are faster than others,
or easier/harder to run locally. The blockchain.info service is recommended
because it presently has the fastest API, and it is the default.

For best privacy, one should query an oracle that is running locally.
Insight, toshi, and btcd can be operated this way.


## btc.com

as of 2018-07-23:

* supports multi address lookup in a single call.
* max addrs per call: unknown.
* returns an index with NULL value for any addresses without received funds.

## blockchain.info

as of 2015-12-30:

* supports multi address lookup in a single call.
* max addrs per call: unknown.
* returns extra un-needed info such as last 50 tx.
* returns addresses in different order than requested.

## blockcypher.com

as of 2018-07-23:

* does support multi address lookup in a single call via batching.
* max addrs per batched call is 100.  however:
* each address is counted as a request internally, and more than
3 triggers the rate limiting, so the request fails.  Thus, 100
can only be achieved with an API key, and the limit for free
usage is effectively 3.
* See https://github.com/blockcypher/explorer/issues/245


## Insight

as of 2015-12-30:

* does NOT support multi address lookup in a single call.
* each candidate address must be queried separately.


## blockr.io

as of 2017-09-04:

* Dead.  Killed by Coinbase.com.
* Read the [obituary](https://www.ccn.com/blockr-io-shuttered-by-coinbase/).
* R.I.P. blockr

as of 2016-02-16:

* supports multi address lookup in a single call.
* limits number of addresses per call to 20.
* does not return un-needed tx data.


## btcd

as of 2017-05-21:

* btcd can now be queried from hd-wallet-addrs to find used wallet addresses, but values for balance/sent/received are empty.
* does not support multi address lookup, so is not that fast.
* is probably the simplest way to run a local oracle.

as of 2015-12-30:

* does not provide a suitable API for querying address total_received or balance.
* does have a public address index that should make such an API possible, if not performant.

## Toshi

as of 2017-05-21:

* toshi.io no longer exists since Dec 31, 2016.
* toshi can still be run locally by installing from github.
* See the Coinbase announcement [here](https://developers.coinbase.com/blog/2016/10/31/sunsetting-toshi).

as of 2015-12-30:

* does NOT support multi address lookup in a single call.
* each candidate address must be queried separately.


## bitcoind

as of 2015-12-30:

* does not provide a suitable API for querying address total_received
* does not have a public address index.  Implementing an API would be difficult.

# Thanks

A big thank-you to the author of bitwasp/bitcoin-php.  This library does the
heavy lifting of dealing with deterministic keys and multisig, amongst other
things.

# Todos

* add option to return only Receive or Change instead of both.
* test with additional wallet software.
* Add bip39 support to obtain xpub from secret words.  maybe?
* Add suitable API to btcd.
