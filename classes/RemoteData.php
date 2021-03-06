<?php

class RemoteData {
	protected $URL;
	protected $USERNAME;
	protected $PASSWORD;
	protected $BASIC_AUTH;
	protected $AUTH_HASH;
	
	public function __construct($URL,$USERNAME,$PASSWORD,$BASIC_AUTH) {
		$this->URL = $URL;
		$this->USERNAME = $USERNAME;
		$this->PASSWORD = $PASSWORD;
		$this->BASIC_AUTH = $BASIC_AUTH;
		
		if (isset($_SESSION['AUTH_HASH'])) {
			$this->AUTH_HASH = $_SESSION['AUTH_HASH'];
		}
		else {
			$this->AUTH_HASH = $this->api_query('user.login', array('password' => $this->PASSWORD, 'user' => $this->USERNAME));
		}
	}
	
	public function __destruct() {
		if (isset($_SESSION['AUTH_HASH'])) {
			unset($_SESSION['AUTH_HASH']);
		}
		$this->AUTH_HASH = null;
	}
	
	public function get_hostgroups($PARAMS) {
		return $this->api_fetch_array('hostgroup.get',$PARAMS);
	}
	
	public function get_triggers($PARAMS) {
		return $this->api_fetch_array('trigger.get',$PARAMS);
	}
	
	public function get_eventdetails($PARAMS) {
		return $this->api_fetch_array('event.get',$PARAMS);
	}
	
	public function add_acknowledge($EVENTID,$MESSAGE) {
		$this->api_query('event.acknowledge',array('eventids' => $EVENTID, 'message' => $MESSAGE, 'action' => 2));
	}
	
	private function api_fetch_array($METHOD, $PARAMS) {
		$RESULT = $this->api_query($METHOD,$PARAMS);
		if (is_array($RESULT))
			return $RESULT;
		else
			return array($RESULT);
	}
	
	private function api_query($METHOD, $PARAMS = array()) {
		if ($this->AUTH_HASH == NULL && $METHOD != 'user.login')
			throw new Exception('No active API login',11);
		
		$DATA_JSON = $this->api_curl( 
			$this->URL,
			json_encode(
				array(
					'auth' => $this->AUTH_HASH,
					'method' => $METHOD,
					'id' => 1,
					'params' => $PARAMS,
					'jsonrpc' => "2.0"
				)
			)
		);
		
		$DATA = json_decode($DATA_JSON, true);

		if (!empty($DATA['result'])) {
			return $DATA['result'];
		} 
		elseif (!empty($DATA['error'])) {
				throw new Exception('API Error: [' . $DATA['error']['code'] . '] ' . $DATA['error']['message'] . ' - ' . $DATA['error']['data'],12);
		}
		else {
			return false;
		}
	}

	private function api_curl($URL, $DATA) {
		$CURL = curl_init($URL);

		$HEADERS = array();
		$HEADERS[]  = 'Content-Type: application/json-rpc';
		$HEADERS[]  = 'User-Agent: ZbxWallboard';

		$CURL_OPTS = array(
			CURLOPT_RETURNTRANSFER => true,	 // Allows for the return of a curl handle
			CURLOPT_TIMEOUT => 30,			  // Maximum number of seconds to allow curl to process the entire request
			CURLOPT_CONNECTTIMEOUT => 5,		// Maximm number of seconds to establish a connection, shouldn't take 5 seconds
			CURLOPT_SSL_VERIFYHOST => false,	// Incase we have a fake SSL Cert...
			CURLOPT_SSL_VERIFYPEER => false,	// Ditto
			CURLOPT_FOLLOWLOCATION => true,	 // Incase there's a redirect in place (moved zabbix url), follow it automatically
			CURLOPT_FRESH_CONNECT => true,	  // Ensures we don't use a cached connection or response
			CURLOPT_ENCODING => 'gzip',
			CURLOPT_HTTPHEADER => $HEADERS,
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_POSTFIELDS => ( is_array($DATA) ? http_build_query($DATA) : $DATA )
		);

		if ($this->BASIC_AUTH === 1) {
			$CURL_OPTS[CURLOPT_HTTPAUTH] = CURLOPT_HTTPAUTH;
			$CURL_OPTS[CURLOPT_USERPWD] = $this->USERNAME . ":" . $this->PASSWORD;
		}
		
		curl_setopt_array($CURL, $CURL_OPTS);
		$RESULT = @curl_exec($CURL);
		curl_close($CURL);
		return $RESULT;
	}
}

?>
