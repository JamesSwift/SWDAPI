<?php

namespace JamesSwift\SWDAPI;

require __DIR__."/../submodules/PHPBootstrap/PHPBootstrap.php";

class SWDAPI extends \JamesSwift\PHPBootstrap\PHPBootstrap {
	
	protected $settings;
	protected $methods;
	
	//Define this method:
	public function loadDefaultConfig(){
		$this->settings = [];
		$this->methods = [];
	}
	
	protected function _sanitizeConfig($config){
		
		$newConfig=[];
		
		//Sanitize methods (if defined)
		if (is_array($config['methods']) && sizeof($config['methods'])>0){
			$newConfig['methods'] = $this->_sanitizeMethods($config['methods']);
		}
		
		
		//Sanitize settings
		
		//none yet
		
		return $newConfig;
		
	}
	

	public function request($methodID, $data=null, $authorizedUser=null){

		//Check we found a method
		if (!isset($this->methods[$methodID])){
			return new Response(404);
		}
		
		//Shorthand
		$method = $this->methods[$methodID];
		
		//Require method src file
		if (isset($method['require']) && is_string($method['require'])){
			require_once($method['require']);
		}
		
		//Attempt to call method
		try {
			
			return call_user_func($method['call'], $data, $authorizedUser);
			
			
		//Catch any unhanlded exceptions and return a 500 message
		} catch (\Exception $e){
			return new Response(500, $e->getMessage());
		}

	}
	
	public function getMethods(){
		return $this->methods;
	}

	protected function _sanitizeMethod($id, $method){
		
		//Check if method already exists
		if (!isset($id) || isset($this->methods[$id])){
			throw new \Exception("A method id definition must be a unique non-empty string.");
		}
		
		//Check call is string
		if (!is_string($method['call']) || strlen($method['call'])<1) {
			throw new \Exception("A method definition must contain an non-empty string named 'call'.");
		}
		
		//Check require exists
		if (isset($method['require'])){
			if (!is_string($method['require']) || !is_file($method['require'])) {
				throw new \Exception("The 'require' path you specified (\"".$method['require']."\") for method (".$method['id'].") doesn't exist.");
			}
		}
		
		return [$id,[
			"require"=>$method['require'],
			"call"=>$method['call']
		]];

	}
	
	protected function _sanitizeMethods($methods){
		
		if (!is_array($methods) || sizeof($methods)<1){
			throw new \Exception("Method _sanitizeMethods requires a non-empty array."); 
		}
		
		$newMethods=[];
		foreach($methods as $id=>$method){
			$newMethod = $this->_sanitizeMethod($id, $method);
			$newMethods[$newMethod[0]]=$newMethod[1];
		}	
		
		return $newMethods;
	}
	
	public function registerMethod($id, $method=null){
		
		//Register single method
		if (is_string($id)){
		
			$method = $this->_sanitizeMethod($id, $method);
		
			$this->methods[$method[0]]=$method[1];
			
			return true;
		
		//Loop through array of methods and register	
		} else if (is_array($id) && sizeof($methods)>0){
			
			foreach($id as $mid=>$method){
				$method = $this->_sanitizeMethod($mid, $method);
				$this->methods[$method[0]]=$method[1];
			}	
			
			return true;
			
		}
		
		return false;
		
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