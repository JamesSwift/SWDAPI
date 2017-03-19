<?php

namespace JamesSwift\SWDAPI;

class Exception extends \Exception {
	//Nothing to do here yet
}

require "../submodules/PHPBootstrap/PHPBootstrap.php";

class SWDAPI extends \JamesSwift\PHPBootstrap\PHPBootstrap {
	
	protected $_config;
	protected $_methods;
	
	//Define this method:
	public function loadDefaultConfig(){
		$this->_config = [];
		$this->_methods = [];
	}
	
	protected function _sanitizeConfig($config){
		$this->registerMethods($config['methods']);
		$this->config=$config['config'];
	}
	

	public function request($method, $data, $authorizedUser=null){
		//Try to find the right method
		$result = $this->findMethod($method);
		
		//Check we found a method
		if ($result===null){
			return new Response(404);
		}
		
		//Carry on
		if (isset($result['require']) && is_string($result['require'])){
			require_once($result['require']);
		}

	}
	
	public function findMethod($method){
		if (isset($this->_methods[$method])){
			return $this->_methods[$method];
		}
		return null;
	}
	
	public function getMethods(){
		return $this->_methods;
	}

	public function registerMethod($method){
		
		//Check if method already exists
		if (!isset($method['id']) || isset($this->_methods[$method['id']])){
			throw new Exception("The array passed to registerMethod must contain a unique non-empty string named 'id'.");
		}
		
		//Check call is string
		if (!is_string($method['call']) || strlen($method['call'])<1) {
			throw new Exception("The array passed to registerMethod must contain an non-empty string named 'call'.");
		}
		
		//Check require exists
		if (isset($method['require'])){
			if (!is_string($method['require']) || !is_file($this->config['requireRoot'].$method['require'])) {
				throw new Exception("The 'require' path you specified (\"".$this->config['requireRoot'].$method['require']."\") doesn't exist.");
			}
		}
		
		//Save it
		$this->_methods[$method['id']]=[
			"require"=>$method['require'],
			"call"=>$method['call']
		];
		
		return true;
	}
	
	public function registerMethods($array){
		if (!is_array($array) || sizeof($array)<1){
			throw new Exception("Method registerMethods requires a non-empty array."); 
		}
		foreach($array as $method){
			$this->registerMethod($method);
		}	
		return true;
	}
}

class Response {
	 public $status;
	 public $data;
	 
	 public function __construct($status=200, $data=null) {
		 $this->status = $status;
		 $this->data = $data;
		 
		 if ($status===404 && $data===null){
		 	$this->data = "Requested method was not found.";
		 }
		 
		 if ($status===403 && $data===null){
		 	$this->data = "Access to the requested method was denied.";
		 }
	 }
}