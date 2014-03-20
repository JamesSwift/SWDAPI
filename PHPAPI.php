<?php

namespace JamesSwift\PHPAPI;

class Exception extends \Exception {
	//Nothing to do here yet
}

class PHPAPI {
	private $config=array();
	private $views=array();
	private $allowedMethods=array("GET","POST","PUT","DELETE");
	
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
		if (is_file($config) && strtolower(substr($config, -5))===".json"){
			//Decode JSON and parse it
			$this->config=json_decode(file_get_contents($config), true);
			return true;
		}
		return false;
	}
	
	public function request($method, $URI, $headers = null, $body = null){
		
	}
	
	public function registerView($view){
		
		//Check Methods array
		if (!isset($view['allowedMethods']) || !is_array($view['allowedMethods']) || sizeof($view['allowedMethods'])<1 ) {
			throw new Exception("The array passed to registerView must contain an non-empty array named 'allowedMethods'.","BadMethodsArrayDefinition");
		}
		foreach($view['allowedMethods'] as &$method){
			$method=strtoupper($method);
			if (in_array($method, $this->allowedMethods)!==true){
				throw new Exception("Unknown method specified in the 'allowedMethods' array.","BadMethodDefinition");
			}
		}
		
		//Check request is string
		if (!is_string($view['request']) || strlen($view['request'])<1) {
			throw new Exception("The array passed to registerView must contain an non-empty string named 'request'.","BadRequestDefinition");
		}
		
		//Check call is string
		if (!is_string($view['call']) || strlen($view['call'])<1) {
			throw new Exception("The array passed to registerView must contain an non-empty string named 'call'.","BadCallDefinition");
		}
		
		//Check require exists
		if (isset($view['require'])){
			if (!is_string($view['require']) || !is_file($view['require'])) {
				throw new Exception("The 'require' path you specified doesn't exist.","BadRequireDefinition");
			}
		}
		
		$this->views[]=array(
			"allowedMethods"=>$view['allowedMethods'],
			"request"=>$view['request'],
			"call"=>$view['call'],
			"require"=>$view['require']
		);
	}
	
	public function registerViews($array){
		if (!is_array($array) || sizeof($array)<1){
			throw new Exception("Method registerView requires a non-empty array.", "InvalidVariableType"); 
		}
		foreach($array as $view){
			$this->registerView($view);
		}
		
	}
}

 class View {
	 public $method;
	 public $URI;
	 public $Headers = array();
	 public $Body;
	 
	public function __construct($method, $URI, $Headers = null, $Body = null) {
		$this->method = $method;
		$this->$URI = $URI;
		$this->$Headers = $Headers;
		$this->$Body = $Body;
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