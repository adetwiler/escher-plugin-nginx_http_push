<?php

class Plugin_nginx_http_push_Helper_http_push extends Helper {
	/* Contains the last HTTP status code returned. */
	public $http_code;
	/* Set timeout default. */
	public $timeout = 30;
	/* Set connect timeout. */
	public $connecttimeout = 30; 
	/* Verify SSL Cert. */
	public $ssl_verifypeer = FALSE;
	/* Respons format. */
	public $format = 'json';
	/* Contains the last HTTP headers returned. */
	public $http_info;
	/* Set the useragnet. */
	public $useragent = 'Escher';

	function publish($id, $data='1') {
		$CFG = Load::CFG();
        if (is_array($CFG['comet']['publish_url'])) {
            foreach ($CFG['comet']['publish_url'] as $url) {
                $this->http($url.'?'.$CFG['comet']['channel_name'].'='.$id,'POST',$data);
            }
        } else {
		    $this->http($CFG['comet']['publish_url'].'?'.$CFG['comet']['channel_name'].'='.$id,'POST',$data);
        }
		if ($this->http_code != '202') { return false; }
		return true;
	}

	function getListenURL($id) {
		$CFG = Load::CFG();
		return $CFG['comet']['listen_url'].'?'.$CFG['comet']['channel_name'].'='.$id;
	}

	/**
	* Make an HTTP request
	*
	* @return results
	*/
	private function http($url, $method, $postfields = NULL) {
		$this->http_info = array();
		$ci = curl_init();
		/* Curl settings */
		curl_setopt($ci, CURLOPT_USERAGENT, $this->useragent);
		curl_setopt($ci, CURLOPT_CONNECTTIMEOUT, $this->connecttimeout);
		curl_setopt($ci, CURLOPT_TIMEOUT, $this->timeout);
		curl_setopt($ci, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ci, CURLOPT_HTTPHEADER, array('Expect:'));
		curl_setopt($ci, CURLOPT_SSL_VERIFYPEER, $this->ssl_verifypeer);
		curl_setopt($ci, CURLOPT_HEADERFUNCTION, array($this, 'getHeader'));
		curl_setopt($ci, CURLOPT_HEADER, FALSE);

		switch ($method) {
			case 'GET':
				curl_setopt($ci, CURLOPT_GET, TRUE);
				break;
			case 'POST':
				curl_setopt($ci, CURLOPT_POST, TRUE);
				if (!empty($postfields)) {
					curl_setopt($ci, CURLOPT_POSTFIELDS, ($this->format == 'json' ? json_encode($postfields) : $postfields));
				}
				break;
			case 'DELETE':
				curl_setopt($ci, CURLOPT_CUSTOMREQUEST, 'DELETE');
				if (!empty($postfields)) {
					$url = "{$url}?{$postfields}";
				}
		}
		curl_setopt($ci, CURLOPT_URL, $url);
		$response = curl_exec($ci);
		$this->http_code = curl_getinfo($ci, CURLINFO_HTTP_CODE);
		$this->http_info = array_merge($this->http_info, curl_getinfo($ci));
		curl_close ($ci);
		return $response;
	}

	/**
	* Get the header info to store.
	*/
	private function getHeader($ch, $header) {
		$i = strpos($header, ':');
		if (!empty($i)) {
			$key = str_replace('-', '_', strtolower(substr($header, 0, $i)));
			$value = trim(substr($header, $i + 2));
			$this->http_header[$key] = $value;
		}
		return strlen($header);
	}
}