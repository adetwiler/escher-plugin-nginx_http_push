<?php

class Plugin_nginx_http_push_Helper_http_push extends Helper {
	/* Contains the last HTTP status code returned. */
	public $http_code;
	/* Set timeout default. */
	public $timeout;
	/* Set connect timeout. */
	public $connecttimeout;
	/* Verify SSL Cert. */
	public $ssl_verifypeer = FALSE;
	/* Respons format. */
	public $format = 'json';
	/* Contains the last HTTP headers returned. */
	public $http_info;
	/* Set the useragnet. */
	public $useragent = 'Escher';

    function __construct($args=array()) {
        $CFG = Load::CFG();

        if (!empty($CFG['comet']['timeout'])) {
            $this->timeout = $CFG['comet']['timeout'];
        } else {
            $this->timeout = 30;
        }
        if (!empty($CFG['comet']['connecttimeout'])) {
            $this->connecttimeout = $CFG['comet']['connecttimeout'];
        } else {
            $this->connecttimeout = 30;
        }

        parent::__construct($args);
    }

	function publish($id, $data='1') {
		$CFG = Load::CFG();
        $pub_url = $CFG['comet']['publish_url'];
        if (!is_array($pub_url)) { $pub_url = (array)$pub_url; }
        $this->http($pub_url,'POST',$CFG['comet']['channel_name'].'='.$id,$data);
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
	private function http($urls, $method, $channel, $postfields = NULL) {
		$this->http_info = array();
		$cm = curl_multi_init();

        $curlHandles = array();
        $responses = array();

        foreach($urls as $id => $url) {
            $c = curl_init();
            /* Curl settings */
            curl_setopt($c, CURLOPT_USERAGENT, $this->useragent);
            curl_setopt($c, CURLOPT_CONNECTTIMEOUT, $this->connecttimeout);
            curl_setopt($c, CURLOPT_TIMEOUT, $this->timeout);
            curl_setopt($c, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($c, CURLOPT_HTTPHEADER, array('Expect:'));
            curl_setopt($c, CURLOPT_SSL_VERIFYPEER, $this->ssl_verifypeer);
            curl_setopt($c, CURLOPT_HEADERFUNCTION, array($this, 'getHeader'));
            curl_setopt($c, CURLOPT_HEADER, FALSE);

            switch ($method) {
                case 'GET':
                    curl_setopt($c, CURLOPT_GET, TRUE);
                    break;
                case 'POST':
                    curl_setopt($c, CURLOPT_POST, TRUE);
                    if (!empty($postfields)) {
                        curl_setopt($c, CURLOPT_POSTFIELDS, ($this->format == 'json' ? json_encode($postfields) : $postfields));
                    }
                    break;
                case 'DELETE':
                    curl_setopt($c, CURLOPT_CUSTOMREQUEST, 'DELETE');
                    if (!empty($postfields)) {
                        $url = "{$url}?{$postfields}";
                    }
            }

            curl_setopt($c, CURLOPT_URL, $url.'?'.$channel);

            $curlHandles[$id] = $c;
            curl_multi_add_handle($cm, $curlHandles[$id]);
        }
        
        $running = null;
        do {
            curl_multi_exec($cm, $running);
        } while($running > 0);

        $result = FALSE;
        foreach($curlHandles as $id => $handle) {
            $responses[$id] = curl_multi_getcontent($handle);
            $result = (bool)curl_multi_remove_handle($cm, $handle) || $result;
        }

        curl_multi_close($cm);

		return $result;
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