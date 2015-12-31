<?php
/**
 * Bitcoin classes
 *
 * By Mike Gogulski - All rights reversed http://www.unlicense.org/ (public domain)
 *
 * If you find this library useful, your donation of Bitcoins to address
 * 1E3d6EWLgwisXY2CWXDcdQQP2ivRN7e9r9 would be greatly appreciated. Thanks!
 *
 * PHPDoc is available at http://code.gogulski.com/
 *
 * @author Mike Gogulski - http://www.nostate.com/ http://www.gogulski.com/
 * @author theymos - theymos @ http://bitcoin.org/smf
 */

require_once(dirname(__FILE__) . "/includes/json_rpc_client.class.php");
 
 
define("BITCOIN_ADDRESS_VERSION", "00");// this is a hex byte
/**
 * Bitcoin utility functions class
 *
 * @author theymos (functionality)
 * @author Mike Gogulski
 * 	http://www.gogulski.com/ http://www.nostate.com/
 *  (encapsulation, string abstraction, PHPDoc)
 */
class Bitcoin {

  /*
   * Bitcoin utility functions by theymos
   * Via http://www.bitcoin.org/smf/index.php?topic=1844.0
   * hex input must be in uppercase, with no leading 0x
   */
  private static $hexchars = "0123456789ABCDEF";
  private static $base58chars = "123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz";

  /**
   * Convert a hex string into a (big) integer
   *
   * @param string $hex
   * @return int
   * @access private
   */
  private function decodeHex($hex) {
    $hex = strtoupper($hex);
    $return = "0";
    for ($i = 0; $i < strlen($hex); $i++) {
      $current = (string) strpos(self::$hexchars, $hex[$i]);
      $return = (string) bcmul($return, "16", 0);
      $return = (string) bcadd($return, $current, 0);
    }
    return $return;
  }

  /**
   * Convert an integer into a hex string
   *
   * @param int $dec
   * @return string
   * @access private
   */
  private function encodeHex($dec) {
    $return = "";
    while (bccomp($dec, 0) == 1) {
      $dv = (string) bcdiv($dec, "16", 0);
      $rem = (integer) bcmod($dec, "16");
      $dec = $dv;
      $return = $return . self::$hexchars[$rem];
    }
    return strrev($return);
  }

  /**
   * Convert a Base58-encoded integer into the equivalent hex string representation
   *
   * @param string $base58
   * @return string
   * @access private
   */
  private function decodeBase58($base58) {
    $origbase58 = $base58;

    $return = "0";
    for ($i = 0; $i < strlen($base58); $i++) {
      $current = (string) strpos(Bitcoin::$base58chars, $base58[$i]);
      $return = (string) bcmul($return, "58", 0);
      $return = (string) bcadd($return, $current, 0);
    }

    $return = self::encodeHex($return);

    //leading zeros
    for ($i = 0; $i < strlen($origbase58) && $origbase58[$i] == "1"; $i++) {
      $return = "00" . $return;
    }

    if (strlen($return) % 2 != 0) {
      $return = "0" . $return;
    }

    return $return;
  }

  /**
   * Convert a hex string representation of an integer into the equivalent Base58 representation
   *
   * @param string $hex
   * @return string
   * @access private
   */
  private function encodeBase58($hex) {
    if (strlen($hex) % 2 != 0) {
      die("encodeBase58: uneven number of hex characters");
    }
    $orighex = $hex;

    $hex = self::decodeHex($hex);
    $return = "";
    while (bccomp($hex, 0) == 1) {
      $dv = (string) bcdiv($hex, "58", 0);
      $rem = (integer) bcmod($hex, "58");
      $hex = $dv;
      $return = $return . self::$base58chars[$rem];
    }
    $return = strrev($return);

    //leading zeros
    for ($i = 0; $i < strlen($orighex) && substr($orighex, $i, 2) == "00"; $i += 2) {
      $return = "1" . $return;
    }

    return $return;
  }

  /**
   * Convert a 160-bit Bitcoin hash to a Bitcoin address
   *
   * @author theymos
   * @param string $hash160
   * @param string $addressversion
   * @return string Bitcoin address
   * @access public
   */
  public static function hash160ToAddress($hash160, $addressversion = BITCOIN_ADDRESS_VERSION) {
    $hash160 = $addressversion . $hash160;
    $check = pack("H*", $hash160);
    $check = hash("sha256", hash("sha256", $check, true));
    $check = substr($check, 0, 8);
    $hash160 = strtoupper($hash160 . $check);
    return self::encodeBase58($hash160);
  }

  /**
   * Convert a Bitcoin address to a 160-bit Bitcoin hash
   *
   * @author theymos
   * @param string $addr
   * @return string Bitcoin hash
   * @access public
   */
  public static function addressToHash160($addr) {
    $addr = self::decodeBase58($addr);
    $addr = substr($addr, 2, strlen($addr) - 10);
    return $addr;
  }

  /**
   * Determine if a string is a valid Bitcoin address
   *
   * @author theymos
   * @param string $addr String to test
   * @param string $addressversion
   * @return boolean
   * @access public
   */
  public static function checkAddress($addr, $addressversion = BITCOIN_ADDRESS_VERSION) {
    $addr = self::decodeBase58($addr);
    if (strlen($addr) != 50) {
      return false;
    }
    $version = substr($addr, 0, 2);
    if (hexdec($version) > hexdec($addressversion)) {
      return false;
    }
    $check = substr($addr, 0, strlen($addr) - 8);
    $check = pack("H*", $check);
    $check = strtoupper(hash("sha256", hash("sha256", $check, true)));
    $check = substr($check, 0, 8);
    return $check == substr($addr, strlen($addr) - 8);
  }

  /**
   * Convert the input to its 160-bit Bitcoin hash
   *
   * @param string $data
   * @return string
   * @access private
   */
  private function hash160($data) {
    $data = pack("H*", $data);
    return strtoupper(hash("ripemd160", hash("sha256", $data, true)));
  }

  /**
   * Convert a Bitcoin public key to a 160-bit Bitcoin hash
   *
   * @param string $pubkey
   * @return string
   * @access public
   */
  public static function pubKeyToAddress($pubkey) {
    return self::hash160ToAddress($this->hash160($pubkey));
  }

  /**
   * Remove leading "0x" from a hex value if present.
   *
   * @param string $string
   * @return string
   * @access public
   */
  public static function remove0x($string) {
    if (substr($string, 0, 2) == "0x" || substr($string, 0, 2) == "0X") {
      $string = substr($string, 2);
    }
    return $string;
  }
}

/**
 * Exception class for BitcoinClient
 *
 * @author Mike Gogulski
 * 	http://www.gogulski.com/ http://www.nostate.com/
 */
class BitcoinClientException extends ErrorException {
  // Redefine the exception so message isn't optional
  public function __construct($message, $code = 0, $severity = E_USER_NOTICE, Exception $previous = null) {
    parent::__construct($message, $code, $severity, $previous);
  }

  public function __toString() {
    return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
  }
}

class BitcoinServerException extends Exception {
  
  protected $currency_symbol;
  
  function __construct( Exception $e, $currency_symbol ) {
    parent::__construct( $e->getMessage(), $e->getCode() );
  }
  
  public function get_currency_symbol( ) {
    return $this->currency_symbol;
  }
}


/**
 * Bitcoin client class for access to a Bitcoin server via JSON-RPC-HTTP[S]
 *
 * Implements the methods documented at https://www.bitcoin.org/wiki/doku.php?id=api
 *
 * @version 0.3.19
 * @author Mike Gogulski
 * 	http://www.gogulski.com/ http://www.nostate.com/
 */
class BitcoinClient extends json_rpc_client {
  
  protected $currency_symbol;

  /**
   * Create a json_rpc_client object to talk to the bitcoin server and return it,
   * or false on failure.
   *
   * @return jsonrpc_client
   * @access public
   * @throws BitcoinClientException
   */
  public function __construct($url, $debug, $currency_symbol) {
    parent::__construct($url, $debug);
    $this->currency_symbol = $currency_symbol;
  }

  /**
   * Test if the connection to the Bitcoin JSON-RPC server is working
   *
   * The check is done by calling the server's getinfo() method and checking
   * for a fault.
   *
   * @return mixed boolean TRUE if successful, or a fault string otherwise
   * @access public
   * @throws none
   */
  public function can_connect() {
    try {
      $r = $this->getinfo();
    } catch (Exception $e) {
      return $e->getMessage();
    }
    return true;
  }


  /**
   * Send a JSON-RPC message and optional parameter arguments to the server.
   *
   * Use the API functions if possible. This method remains public to support
   * changes being made to the API before this libarary can be updated.
   *
   * @param string $message
   * @param mixed $args, ...
   * @return mixed
   * @throws BitcoinClientException
   * @see xmlrpc.inc:php_xmlrpc_decode()
   */
  public function query($method) {
    try {
      return $this->__call( $method, array_slice( func_get_args(), 1 ) );
    }
    catch( Exception $e) {
      throw new BitcoinServerException( $e, $this->currency_symbol );
    }
  }

  /*
   * The following functions implement the Bitcoin RPC API as documented at https://www.bitcoin.org/wiki/doku.php?id=api
   */

  /**
   * Safely copies wallet.dat to destination, which can be a directory or
   * a path with filename.
   *
   * @param string $destination
   * @return mixed Nothing, or an error array
   * @throws BitcoinClientException
   */
  public function backupwallet($destination) {
    if (!$destination || empty($destination))
      throw new BitcoinClientException("backupwallet requires a destination");
    return $this->query("backupwallet", $destination);
  }

  /**
   * Returns the server's available balance, or the balance for $account with
   * at least $minconf confirmations.
   *
   * @param string $account Account to check. If not provided, the server's
   *  total available balance is returned.
   * @param integer $minconf If specified, only transactions with at least
   *  $minconf confirmations will be included in the returned total.
   * @return float Bitcoin balance
   * @throws BitcoinClientException
   */
  public function getbalance($account = NULL, $minconf = 1) {
    if (!is_numeric($minconf) || $minconf < 0)
      throw new BitcoinClientException('getbalance requires a numeric minconf >= 0');
    if ($account == NULL)
      return $this->query("getbalance");
    return $this->query("getbalance", $account, $minconf);
  }

  /**
   * Returns the number of blocks in the longest block chain.
   *
   * @return integer Current block count
   * @throws BitcoinClientException
   */
  public function getblockcount() {
    return $this->query("getblockcount");
  }

  /**
   * Returns the block number of the latest block in the longest block chain.
   *
   * @return integer Block number
   * @throws BitcoinClientException
   */
  public function getblocknumber() {
    return $this->query("getblocknumber");
  }

  /**
   * Returns the number of connections to other nodes.
   *
   * @return integer Connection count
   * @throws BitcoinClientException
   */
  public function getconnectioncount() {
    return $this->query("getconnectioncount");
  }

  /**
   * Returns the proof-of-work difficulty as a multiple of the minimum difficulty.
   *
   * @return float Difficulty
   * @throws BitcoinClientException
   */
  public function getdifficulty() {
    return $this->query("getdifficulty");
  }

  /**
   * Returns boolean true if server is trying to generate bitcoins, false otherwise.
   *
   * @return boolean Generation status
   * @throws BitcoinClientException
   */
  public function getgenerate() {
    return $this->query("getgenerate");
  }

  /**
   * Tell Bitcoin server to generate Bitcoins or not, and how many processors
   * to use.
   *
   * @param boolean $generate
   * @param integer $maxproc
   * 	Limit generation to $maxproc processors, unlimited if -1
   * @return mixed Nothing if successful, error array if not
   * @throws BitcoinClientException
   */
  public function setgenerate($generate = TRUE, $maxproc = -1) {
    if (!is_numeric($maxproc) || $maxproc < -1)
      throw new BitcoinClientException('setgenerate: $maxproc must be numeric and >= -1');
    return $this->query("setgenerate", $generate, $maxproc);
  }

  /**
   * Returns an array containing server information.
   *
   * @return array Server information
   * @throws BitcoinClientException
   */
  public function getinfo() {
    return $this->query("getinfo");
  }

  /**
   * Returns the account associated with the given address.
   *
   * @param string $address
   * @return string Account
   * @throws BitcoinClientException
   * @since 0.3.17
   */
  public function getaccount($address) {
    if (!$address || empty($address))
      throw new BitcoinClientException("getaccount requires an address");
    return $this->query("getaccount", $address);
  }

  /**
   * Returns the label associated with the given address.
   *
   * @param string $address
   * @return string Label
   * @throws BitcoinClientException
   * @deprecated Since 0.3.17
   */
  public function getlabel($address) {
    if (!$address || empty($address))
      throw new BitcoinClientException("getlabel requires an address");
    return $this->query("getlabel", $address);
  }

  /**
   * Sets the account associated with the given address.
   * $account may be omitted to remove an account from an address.
   *
   * @param string $address
   * @param string $account
   * @return NULL
   * @throws BitcoinClientException
   * @since 0.3.17
   */
  public function setaccount($address, $account = "") {
    if (!$address || empty($address))
      throw new BitcoinClientException("setaccount requires an address");
    return $this->query("setaccount", $address, $account);
  }

  /**
   * Sets the label associated with the given address.
   * $label may be omitted to remove a label from an address.
   *
   * @param string $address
   * @param string $label
   * @return NULL
   * @throws BitcoinClientException
   * @deprecated Since 0.3.17
   */
  public function setlabel($address, $label = "") {
    if (!$address || empty($address))
      throw new BitcoinClientException("setlabel requires an address");
    return $this->query("setlabel", $address, $label);
  }

  /**
   * Returns a new bitcoin address for receiving payments.
   *
   * If $account is specified (recommended), it is added to the address book so
   * payments received with the address will be credited to $account.
   *
   * @param string $account Label to apply to the new address
   * @return string Bitcoin address
   * @throws BitcoinClientException
   */
  public function getnewaddress($account = NULL) {
    if (!$account || empty($account))
      return $this->query("getnewaddress");
    return $this->query("getnewaddress", $account);
  }

  /**
   * Returns the total amount received by $address in transactions with at least
   * $minconf confirmations.
   *
   * @param string $address
   * 	Bitcoin address
   * @param integer $minconf
   * 	Minimum number of confirmations for transactions to be counted
   * @return float Bitcoin total
   * @throws BitcoinClientException
   */
  public function getreceivedbyaddress($address, $minconf = 1) {
    if (!is_numeric($minconf) || $minconf < 0)
      throw new BitcoinClientException('getreceivedbyaddress requires a numeric minconf >= 0');
    if (!$address || empty($address))
      throw new BitcoinClientException("getreceivedbyaddress requires an address");
    return $this->query("getreceivedbyaddress", $address, $minconf);
  }

  /**
   * Returns the total amount received by addresses associated with $account
   * in transactions with at least $minconf confirmations.
   *
   * @param string $account
   * @param integer $minconf
   * 	Minimum number of confirmations for transactions to be counted
   * @return float Bitcoin total
   * @throws BitcoinClientException
   * @since 0.3.17
   */
  public function getreceivedbyaccount($account, $minconf = 1) {
    if (!is_numeric($minconf) || $minconf < 0)
      throw new BitcoinClientException('getreceivedbyaccount requires a numeric minconf >= 0');
    if (!$account || empty($account))
      throw new BitcoinClientException("getreceivedbyaccount requires an account");
    return $this->query("getreceivedbyaccount", $account, $minconf);
  }

  /**
   * Returns the total amount received by addresses with $label in
   * transactions with at least $minconf confirmations.
   *
   * @param string $label
   * @param integer $minconf
   * 	Minimum number of confirmations for transactions to be counted
   * @return float Bitcoin total
   * @throws BitcoinClientException
   * @deprecated Since 0.3.17
   */
  public function getreceivedbylabel($label, $minconf = 1) {
    if (!is_numeric($minconf) || $minconf < 0)
      throw new BitcoinClientException('getreceivedbylabel requires a numeric minconf >= 0');
    if (!$label || empty($label))
      throw new BitcoinClientException("getreceivedbylabel requires a label");
    return $this->query("getreceivedbylabel", $label, $minconf);
  }

  /**
   * Return a list of server RPC commands or help for $command, if specified.
   *
   * @param string $command
   * @return string Help text
   * @throws BitcoinClientException
   */
  public function help($command = NULL) {
    if (!$command || empty($command))
      return $this->query("help");
    return $this->query("help", $command);
  }

  /**
   * Return an array of arrays showing how many Bitcoins have been received by
   * each address in the server's wallet.
   *
   * @param integer $minconf Minimum number of confirmations before payments are included.
   * @param boolean $includeempty Whether to include addresses that haven't received any payments.
   * @return array An array of arrays. The elements are:
   * 	"address" => receiving address
   * 	"account" => the account of the receiving address
   * 	"amount" => total amount received by the address
   * 	"confirmations" => number of confirmations of the most recent transaction included
   * @throws BitcoinClientException
   */
  public function listreceivedbyaddress($minconf = 1, $includeempty = FALSE) {
    if (!is_numeric($minconf) || $minconf < 0)
      throw new BitcoinClientException('listreceivedbyaddress requires a numeric minconf >= 0');
    return $this->query("listreceivedbyaddress", $minconf, $includeempty);
  }

  /**
   * Return an array of arrays showing how many Bitcoins have been received by
   * each account in the server's wallet.
   *
   * @param integer $minconf
   * 	Minimum number of confirmations before payments are included.
   * @param boolean $includeempty
   * 	Whether to include addresses that haven't received any payments.
   * @return array An array of arrays. The elements are:
   * 	"account" => the label of the receiving address
   * 	"amount" => total amount received by the address
   * 	"confirmations" => number of confirmations of the most recent transaction included
   * @throws BitcoinClientException
   * @since 0.3.17
   */
  public function listreceivedbyaccount($minconf = 1, $includeempty = FALSE) {
    if (!is_numeric($minconf) || $minconf < 0)
      throw new BitcoinClientException('listreceivedbyaccount requires a numeric minconf >= 0');
    return $this->query("listreceivedbyaccount", $minconf, $includeempty);
  }

  /**
   * Return an array of arrays showing how many Bitcoins have been received by
   * each label in the server's wallet.
   *
   * @param integer $minconf Minimum number of confirmations before payments are included.
   * @param boolean $includeempty Whether to include addresses that haven't received any payments.
   * @return array An array of arrays. The elements are:
   * 	"label" => the label of the receiving address
   * 	"amount" => total amount received by the address
   * 	"confirmations" => number of confirmations of the most recent transaction included
   * @throws BitcoinClientException
   * @deprecated Since 0.3.17
   */
  public function listreceivedbylabel($minconf = 1, $includeempty = FALSE) {
    if (!is_numeric($minconf) || $minconf < 0)
      throw new BitcoinClientException('listreceivedbylabel requires a numeric minconf >= 0');
    return $this->query("listreceivedbylabel", $minconf, $includeempty);
  }

  /**
   * Returns up to [count] most recent transactions skipping the first [from]
   * transactions for account [account]. If [account] not provided will return
   * recent transaction from all accounts. 
   *
   * @param string  $account  account to check transactions for, if any.
   * @param integer $count    maximum number of transactions
   * @param integer $from     number of transactions to skip.
   * 
   * @return array An array of arrays. The elements are:
   * 	"label" => the label of the receiving address
   * 	"amount" => total amount received by the address
   * 	"confirmations" => number of confirmations of the most recent transaction included
   * @throws BitcoinClientException
   */
  public function listtransactions($account = null, $count = 10, $from = 0) {
    if (!is_numeric($count) || $count <= 0) {
      throw new BitcoinClientException('listtransactions requires a numeric count > 0');
    }
    if (!is_numeric($from) || $from < 0) {
      throw new BitcoinClientException('listtransactions requires a numeric from >= 0');
    }
    return $this->query("listtransactions", $account, $count, $from);
  }
  

  /**
   *  Get all transactions in blocks since block [blockhash], or all
   *  transactions if omitted.
   *
   * @throws BitcoinClientException
   */
  public function listsinceblock($blockhash, $target_confirmations = null) {
    return $this->query("listsinceblock", $blockhash, $target_confirmations);
  }

  
  /**
   * Send amount from the server's available balance.
   *
   * $amount is a real and is rounded to the nearest 0.01. Returns string "sent" on success.
   *
   * @param string $address Destination Bitcoin address or IP address
   * @param float $amount Amount to send. Will be rounded to the nearest 0.01.
   * @param string $comment
   * @param string $comment_to
   * @return string Hexadecimal transaction ID on success.
   * @throws BitcoinClientException
   * @todo Document the comment arguments better.
   */
  public function sendtoaddress($address, $amount, $comment = NULL, $comment_to = NULL) {
    if (!$address || empty($address))
      throw new BitcoinClientException("sendtoaddress requires a destination address");
    if (!$amount || empty($amount))
      throw new BitcoinClientException("sendtoaddress requires an amount to send");
    if (!is_numeric($amount) || $amount <= 0)
      throw new BitcoinClientException("sendtoaddress requires the amount sent to be a number > 0");
    $amount = floatval($amount);
    if (!$comment && !$comment_to)
      return $this->query("sendtoaddress", $address, $amount);
    if (!$comment_to)
      return $this->query("sendtoaddress", $address, $amount, $comment);
    return $this->query("sendtoaddress", $address, $amount, $comment, $comment_to);
  }

  public function sendmany($account, $address_amount_map, $minconf=1, $comment = null) {
    
    if (!$address_amount_map || @empty($address_amount_map)) {
      throw new BitcoinClientException("sendtoaddress requires destination address(es)");
    }
    
    if (!$comment) {
      return $this->query("sendmany", $account, $address_amount_map, $minconf);
    }
    return $this->query("sendmany", $account, $address_amount_map, $minconf, $comment );
  }
  
  
  /**
   * Stop the Bitcoin server.
   *
   * @throws BitcoinClientException
   */
  public function stop() {
    return $this->query("stop");
  }

  /**
   * Check that $address looks like a proper Bitcoin address.
   *
   * @param string $address String to test for validity as a Bitcoin address
   * @return array An array containing:
   * 	"isvalid" => true or false
   * 	"ismine" => true if the address is in the server's wallet
   * 	"address" => bitcoinaddress
   *  Note: ismine and address are only returned if the address is valid.
   * @throws BitcoinClientException
   */
  public function validateaddress($address) {
    if (!$address || empty($address))
      throw new BitcoinClientException("validateaddress requires a Bitcoin address");
    return $this->query("validateaddress", $address);
  }

  /**
   * Return information about a specific transaction.
   *
   * @param string $txid 64-digit hexadecimal transaction ID
   * @return array An error array, or an array containing:
   *    "amount" => float Transaction amount
   *    "fee" => float Transaction fee
   *    "confirmations" => integer Network confirmations of this transaction
   *    "txid" => string The transaction ID
   *    "message" => string Transaction "comment" message
   *    "to" => string Transaction "to" message
   * @throws BitcoinClientException
   * @since 0.3.18
   */
  public function gettransaction($txid) {
    if (!$txid || empty($txid) || strlen($txid) != 64 || !preg_match('/^[0-9a-fA-F]+$/', $txid))
      throw new BitcoinClientException("gettransaction requires a valid hexadecimal transaction ID");
    return $this->query("gettransaction", $txid);
  }

  /**
   * Move bitcoins between accounts.
   *
   * @param string $fromaccount
   *    Account to move from. If given as an empty string ("") or NULL, bitcoins will
   *    be moved from the wallet balance to the target account.
   * @param string $toaccount
   *     Account to move to
   * @param float $amount
   *     Amount to move
   * @param integer $minconf
   *     Minimum number of confirmations on bitcoins being moved
   * @param string $comment
   *     Transaction comment
   * @throws BitcoinClientException
   * @since 0.3.18
   */
  public function move($fromaccount = "", $toaccount, $amount, $minconf = 1, $comment = NULL) {
    if (!$fromaccount)
      $fromaccount = "";
    if (!$toaccount || empty($toaccount) || !$amount || !is_numeric($amount) || $amount <= 0)
      throw new BitcoinClientException("move requires a from account, to account and numeric amount > 0");
    if (!is_numeric($minconf) || $minconf < 0)
      throw new BitcoinClientException('move requires a numeric $minconf >= 0');
    if (!$comment || empty($comment))
      return $this->query("move", $fromaccount, $toaccount, $amount, $minconf);
    return $this->query("move", $fromaccount, $toaccount, $amount, $minconf, $comment);
  }

  /**
   * Send $amount from $account's balance to $toaddress. This method will fail
   * if there is less than $amount bitcoins with $minconf confirmations in the
   * account's balance (unless $account is the empty-string-named default
   * account; it behaves like the sendtoaddress method). Returns transaction
   * ID on success.
   *
   * @param string $account Account to send from
   * @param string $toaddress Bitcoin address to send to
   * @param float $amount Amount to send
   * @param integer $minconf Minimum number of confirmations on bitcoins being sent
   * @param string $comment
   * @param string $comment_to
   * @return string Hexadecimal transaction ID
   * @throws BitcoinClientException
   * @since 0.3.18
   */
  public function sendfrom($account, $toaddress, $amount, $minconf = 1, $comment = NULL, $comment_to = NULL) {
    if (!$account || !$toaddress || empty($toaddress) || !$amount || !is_numeric($amount) || $amount <= 0)
      throw new BitcoinClientException("sendfrom requires a from account, to account and numeric amount > 0");
    if (!is_numeric($minconf) || $minconf < 0)
      throw new BitcoinClientException('sendfrom requires a numeric $minconf >= 0');
    if (!$comment && !$comment_to)
      return $this->query("sendfrom", $account, $toaddress, $amount, $minconf);
    if (!$comment_to)
      return $this->query("sendfrom", $account, $toaddress, $amount, $minconf, $comment);
    $this->query("sendfrom", $account, $toaddress, $amount, $minconf, $comment, $comment_to);
  }

  /**
   * Return formatted hash data to work on, or try to solve specified block.
   *
   * If $data is provided, tries to solve the block and returns true if successful.
   * If $data is not provided, returns formatted hash data to work on.
   *
   * @param string $data Block data
   * @return mixed
   *    boolean TRUE if $data provided and block solving successful
   *    array otherwise, containing:
   *      "midstate" => string, precomputed hash state after hashing the first half of the data
   *      "data" => string, block data
   *      "hash1" => string, formatted hash buffer for second hash
   *      "target" => string, little endian hash target
   * @throws BitcoinClientException
   * @since 0.3.18
   */
  public function getwork($data = NULL) {
    if (!$data)
      return $this->query("getwork");
    return $this->query("getwork", $data);
  }

  /**
   * Return the current bitcoin address for receiving payments to $account.
   * The account and address will be created if $account doesn't exist.
   *
   * @param string $account Account name
   * @return string Bitcoin address for $account
   * @throws BitcoinClientException
   * @since 0.3.18
   */
  public function getaccountaddress($account) {
    if (!$account || empty($account))
      throw new BitcoinClientException("getaccountaddress requires an account");
    return $this->query("getaccountaddress", $account);
  }

  /**
   * Return a recent hashes per second performance measurement.
   *
   * @return integer Hashes per second
   * @throws BitcoinClientException
   */
  public function gethashespersec() {
    return $this->query("gethashespersec");
  }

  /**
   * Returns the list of addresses associated with the given account.
   *
   * @param string $account
   * @return array
   *    A simple array of Bitcoin addresses associated with $account, empty
   *    if the account doesn't exist.
   * @throws BitcoinClientException
   */
  public function getaddressesbyaccount($account) {
    if (!$account || empty($account))
      throw new BitcoinClientException("getaddressesbyaccount requires an account");
    return $this->query("getaddressesbyaccount", $account);
  }

  public function createrawtransaction($inputs, $outputs) {
    if( !$inputs || !count( $inputs ) ) {
      throw new Exception( "No transaction inputs!" );
    }
    if( !$outputs || !count( $outputs ) ) {
      throw new Exception( "No transaction outputs!" );
    }
    return $this->query("createrawtransaction", $inputs, $outputs );
  }
  
  public function signrawtransaction($rawtx) {
    if( !$rawtx ) {
      throw new Exception( "No raw transaction data" );
    }
    return $this->query("signrawtransaction", $rawtx );
  }

  public function sendrawtransaction($rawtx_signed) {
    if( !$rawtx_signed ) {
      throw new Exception( "No signed raw transaction data" );
    }
    return $this->query("sendrawtransaction", $rawtx_signed );
  }

  public function listunspent( $minconf=1, $maxconf=9999999, $addrs = array() ) {
    return $this->query("listunspent", $minconf, $maxconf, $addrs );
  }
  
  public function is_testnet() {
    $info = $this->getinfo();
    return @$info->testnet == true;
  }

  
}
