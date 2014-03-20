<?php

namespace \JamesSwift\PHPAPI;

class PHPAPI {
	private $config;
	
	public function __construct($config){
		$this->loadConfig($config);
	}
	
	public function loadConfig($config){
		//If config is an assoc array, just load it
		if (is_array($config)){
			$this->config = $config;
			return true;
		//If config is a path to a json file, parse and load it
		} else {
			return $this->loadConfigFromJSON($config);
		}	
	}
	
	public function loadConfigFromJSON($config){
		//Check JSON file exists and has right extension
		if (is_file($config) && str_to_lower(substr($config, -5))===".json"){
			//Decode JSON and parse it
			$this->config=json_decode(file_get_contents($config), true);
			return true;
		}
		return false;
	}
}

 class View {
	 public $method;
	 public $requestURI;
	 public $requestHeaders = array();
	 public $requestBody;
	 
	public function __construct($method, $requestURI, $requestHeaders = null, $requestBody = null) {
		$this->method = $method;
		$this->$requestURI = $requestURI;
		$this->$requestHeaders = $requestHeaders;
		$this->$requestBody = $requestBody;
	}
 }
 
 class Response {
	 public $status;
	 public $headers;
	 public $body;
	 
	 public function __construct($status=200, $body=array(), $headers=array()) {
		 $this->status=$status;
		 $this->body=$body;
		 $headers->headers=$headers;
	 }
 }