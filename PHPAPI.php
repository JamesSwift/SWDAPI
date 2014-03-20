<?php

namespace JamesSwift\PHPAPI;

class Exception extends \Exception {
	//Nothing to do here yet
}

class PHPAPI {
	private $config;
	private $views;
	private $allowedMethods=array("GET","POST","PUT","DELETE");
	
	public function __construct($config=null){
		$this->loadConfig($config);
	}
	
	public function loadConfig($config){
		//If config is an assoc array, just load it
		if (is_array($config)){
			$this->config = $config;
			return true;
		//If config is a path to a json file, parse and load it
		} else if ($config!==null){
			return $this->loadJSON($config);
		}	
	}
	
	public function loadJSON($config){
		//Check JSON file exists and has right extension
		if (is_file($config) && strtolower(substr($config, -5))===".json"){
			//Decode JSON and parse it
			$file=json_decode(file_get_contents($config), true);
			$this->registerViews($file['views']);
			$this->config=$file['config'];
			return true;
		}
		return false;
	}
	
	public function request($method, $URI, $headers = null, $body = null){
		//Try to find the right view
		$view=$this->findViewByURI($URI);
		var_dump($view);
	}
	
	public function findViewByURI($URI){
		foreach ($this->views as $view){
			if (preg_match($view['pattern'], $URI)==true){
				return $view;
			}
		}
		return null;
	}
	
	public function getViews(){
		return $this->views;
	}
	
	public function set($what, $to){
		$this->config[$what]=$to;
	}
	
	public function registerView($view){
		
		//Check Methods array
		if (!isset($view['allowedMethods']) || !is_array($view['allowedMethods']) || sizeof($view['allowedMethods'])<1 ) {
			throw new Exception("The array passed to registerView must contain an non-empty array named 'allowedMethods'.");
		}
		foreach($view['allowedMethods'] as &$method){
			$method=strtoupper($method);
			if (in_array($method, $this->allowedMethods)!==true){
				throw new Exception("Unknown method specified in the 'allowedMethods' array.");
			}
		}
		
		//Check request is string
		if (!is_string($view['pattern']) || strlen($view['pattern'])<1) {
			throw new Exception("The array passed to registerView must contain an non-empty string named 'pattern'.");
		}
		
		//Check call is string
		if (!is_string($view['call']) || strlen($view['call'])<1) {
			throw new Exception("The array passed to registerView must contain an non-empty string named 'call'.");
		}
		
		//Check require exists
		if (isset($view['require'])){
			if (!is_string($view['require']) || !is_file($this->config['requireRoot'].$view['require'])) {
				throw new Exception("The 'require' path you specified (\"".$this->config['requireRoot'].$view['require']."\") doesn't exist.");
			}
		}
		
		$this->views[]=array(
			"allowedMethods"=>$view['allowedMethods'],
			"pattern"=>$view['pattern'],
			"call"=>$view['call'],
			"require"=>$view['require']
		);
	}
	
	public function registerViews($array){
		if (!is_array($array) || sizeof($array)<1){
			throw new Exception("Method registerView requires a non-empty array."); 
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