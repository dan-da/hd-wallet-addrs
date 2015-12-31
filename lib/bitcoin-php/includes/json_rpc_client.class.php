<?php
/*
                    COPYRIGHT

Copyright 2007 Sergio Vaccaro <sergio@inservibile.org>

This file is part of JSON-RPC PHP.

JSON-RPC PHP is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

JSON-RPC PHP is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with JSON-RPC PHP; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/**
 * The object of this class are generic jsonRPC 1.0 clients
 * http://json-rpc.org/wiki/specification
 *
 * @author sergio <jsonrpcphp@inservibile.org>
 */
class json_rpc_client {
    
    /**
     * Debug state
     *
     * @var boolean
     */
    private $debug;
    
    /**
     * The server URL
     *
     * @var string
     */
    private $url;
    /**
     * The request id
     *
     * @var integer
     */
    private $id;
    /**
     * If true, notifications are performed instead of requests
     *
     * @var boolean
     */
    private $notification = false;

    /**
     * copy of last response body from server.
     *
     * @var string
     */
    private $last_response;
    
    /**
     * Takes the connection parameters
     *
     * @param string $url
     * @param boolean $debug
     */
    public function __construct($url,$debug = false) {
        // server URL
        $this->url = $url;
        // proxy
        empty($proxy) ? $this->proxy = '' : $this->proxy = $proxy;
        // debug state
        empty($debug) ? $this->debug = false : $this->debug = true;
        // message id
        $this->id = 1;
    }
    
    /**
     * Sets the notification state of the object. In this state, notifications are performed, instead of requests.
     *
     * @param boolean $notification
     */
    public function setRPCNotification($notification) {
        empty($notification) ?
                            $this->notification = false
                            :
                            $this->notification = true;
    }
    
    
    public function __call2($method,$params) {
        
        // make params indexed array of values
        $params = array_values($params);
        
        // prepares the request
        $request = json_encode(array(
                                    'method' => strtolower($method),
                                    'params' => $params,
                                    'id' => $this->id
        ));
        
        // performs the HTTP POST using curl
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, Array("Content-type: application/json"));
        curl_setopt($curl, CURLOPT_URL, $this->url);
        curl_setopt($curl, CURLOPT_USERPWD, $this->username.":".$this->password);
        curl_setopt($curl, CURLOPT_POST, TRUE);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $request);
        $response = curl_exec($curl);
        curl_close($curl);
        
        // process response
        if (!$response) {
            throw new Exception('Unable to connect to '.$this->url, 0);
        }
        $this->last_response = $response;
        $response = json_decode($response,true);
        
        // check response id
        if ($response['id'] != $this->id) {
            throw new Exception('Incorrect response id (request id: '.$this->id.', response id: '.$response['id'].')',1);
        }
        if (!is_null($response['error'])) {
            if( @$response['error']['code'] && @$response['error']['message'] ) {
                throw new Exception( @$response['error']['message'], @$response['error']['code'] );
            }
            else {
                throw new Exception('Request error: '.print_r($response['error'],1),2);
            }
        }
        $this->id++;
        
        // return
        return $response['result'];
    }
    
    /**
     * Performs a jsonRCP request and gets the results as an array
     *
     * @param string $method
     * @param array $params
     * @return array
     */
    public function __call($method,$params) {
        
        // check
        if (!is_scalar($method)) {
            throw new Exception('Method name has no scalar value');
        }
        
        // check
        if (!is_array($params)) {
            throw new Exception('Params must be given as array');
        }
        
        // sets notification or request task
        if ($this->notification) {
            $currentId = NULL;
        } else {
            $currentId = $this->id;
        }
        
        // prepares the request
        $request = array(
                        'jsonrpc' => '1.0',
                        'method' => $method,
                        'params' => $params,
                        'id' => $currentId
                        );
        $request = json_encode($request);
        $this->debug && $this->debug.='***** Request *****'."\n".$request."\n".'***** End Of request *****'."\n\n";

        // debug output
        if ($this->debug) {
            echo $this->debug;
            $this->debug = true;
        }

        // performs the HTTP POST using curl
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, Array("Content-type: application/json"));
        curl_setopt($curl, CURLOPT_URL, $this->url);
//        curl_setopt($curl, CURLOPT_USERPWD, $this->username.":".$this->password);
        curl_setopt($curl, CURLOPT_POST, TRUE);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $request);
        $response = curl_exec($curl);
        curl_close($curl);

        // process response
        if (!$response) {
            throw new Exception('Unable to connect to '.$this->url, 0);
        }
        
        $this->debug && $this->debug.='***** Server response *****'."\n".$response.'***** End of server response *****'."\n";
        // debug output
        if ($this->debug) {
            echo $this->debug;
        }
        
        $this->last_response = $response;
        $response = json_decode($response,true);
        
        // check response id
        if (@$response['id'] != $this->id) {
            throw new Exception('Incorrect response id (request id: '.$this->id.', response id: '.@$response['id'].')',1);
        }
        if (!is_null(@$response['error'])) {
            if( @$response['error']['code'] && @$response['error']['message'] ) {
                throw new Exception( @$response['error']['message'], @$response['error']['code'] );
            }
            else {
                throw new Exception('Request error: '.print_r(@$response['error'],1),2);
            }
        }
        $this->id++;
        
        // return
        return @$response['result'];

    }

    /**
     * Returns body of last server response, or null.
     *
     * @return string
     */
    public function last_response() {
        return $this->last_response;
    }
}
?>
