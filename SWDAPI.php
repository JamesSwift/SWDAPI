<?php

namespace JamesSwift\SWDAPI;

require "submodules/PHPBootstrap/PHPBootstrap.php";

class SWDAPI extends \JamesSwift\PHPBootstrap\PHPBootstrap {
	
	protected $settings;
	protected $methods;
	
	//////////////////////////////////////////////////////////////////////
	// Methods required by PHPBootstrap
	
	public function loadDefaultConfig(){
		$this->settings = [];
		$this->methods = [];
	}
	
	protected function _sanitizeConfig($config){
		
		$newConfig=[];

		//Sanitize settings (if defined)
		if (is_array($config['settings']) && sizeof($config['settings'])>0){
			$newConfig['settings'] = $this->_sanitizeSettings($config['settings']);
		}
		
		
		//Sanitize methods (if defined)
		if (is_array($config['methods']) && sizeof($config['methods'])>0){
			$newConfig['methods'] = $this->_sanitizeMethods($config['methods']);
		}

		return $newConfig;
		
	}
	
	// EO methods required by PHPBootstrap
	//////////////////////////////////////////////////////////////////////
	
	public function request($methodID, $data=null, $authInfo=null){

		//Check we found a method
		if (!isset($this->methods[$methodID])){
			return new Response(404);
		}
		
		//Shorthand
		$method = $this->methods[$methodID];
		
		//Does this method require an authorized user?
		if (isset($method['requireAuthorizedUser']) && $method['requireAuthorizedUser']===true){
			
			if (!isset($authInfo['authorizedUser']) || $authInfo['authorizedUser']==null ){
				return new Response(403);
			}
			
		}
		
		//Require method src file
		if (isset($method['src']) && is_array($method['src']) && sizeof($method['src'])>0 ){
			if (isset($settings['methodSrcRoot'])){
				foreach ($method['src'] as $src){
					require_once($settings['methodSrcRoot'].$src);
				}
			} else {
				foreach ($method['src'] as $src){
					require_once($src);
				}
			}
		}
		
		//Attempt to call method
		try {
			
			return call_user_func($method['call'], $data, $authInfo);
			
			
		//Catch any unhanlded exceptions and return a 500 message
		} catch (\Exception $e){
			return new Response(500, $e->getMessage());
		}

	}
	
	public function getMethods(){
		return $this->methods;
	}

	protected function _sanitizeMethod($id, $method){
		
		$newMethod=[];
		
		//Check if method already exists
		if (!isset($id) || isset($this->methods[$id])){
			throw new \Exception("A method id definition must be a unique non-empty string.");
		}
		
		//Check id is something sensible
		$newID=preg_replace("[^0-9a-zA-Z/\\\-\._]", "", $id);
		if ($newID!==$id){
			throw new \Exception("A method id definition must only contain the characters: 0-9 a-Z \\ / - _ .");
		}
		
		//Check call is string
		if (!is_string($method['call']) || strlen($method['call'])<1) {
			throw new \Exception("A method definition must contain an non-empty string named 'call'.");
		}
		
		//Check call is something sensible
		$newMethod['call']=preg_replace("[^0-9a-zA-Z\\_]", "", $method['call']);
		if ($newMethod['call']!==$method['call']){
			throw new \Exception("A method call definition must only contain the characters: 0-9 a-Z \\ _");
		}		
		
		//Src - convert string to array
		if (is_string($method['src'])){
			$method['src']=[$method['src']];
		}
		
		//Loop through src array
		if (is_array($method['src']) && sizeof($method['src'])>0){
			$newMethod['src']=[];
			
			foreach($method['src'] as $src){
				
				if (!is_string($src)){
					throw new \Exception("A method src definition must be either a string or an array of strings.");
				}
				
				//Sanitize src filename
				$src=$this->sanitizeFilePath($src);		
			
				//Check it exists
				$path = $src;
				if (isset($this->settings['methodSrcRoot'])){
					$path = $this->settings['methodSrcRoot'] . $path;
				}
				if (!is_file($path)) {
					throw new \Exception("The 'src' path you specified (".$path.") for method (".$newID.") doesn't exist.");
				}
				$newMethod['src'][]=$src;
			
			}
		}
		
		//Check requireAuthorizedUser is bool
		if (isset($method['requireAuthorizedUser'])){
			if (!is_bool($method['requireAuthorizedUser'])){
				throw new \Exception("A method 'requireAuthorizedUser' definition must be type boolean if it exists.");
			}
			if ($method['requireAuthorizedUser']===true){
				$newMethod['requireAuthorizedUser']=true;
			}
			
		}
		
		return [$newID, $newMethod];

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
	
	protected function _sanitizeSettings($settings){
		
		$newSettings = [];

		//methodSrcRoot - you can optionaly specify the root path that method['src'] will atempt to resolve from
		if (isset($settings['methodSrcRoot'])){
			$newSettings['methodSrcRoot'] = $this->sanitizeFilePath($settings['methodSrcRoot'], false, true);
			
			//load it straight away, as other sanitization filters rely on this
			$this->settings['methodSrcRoot'] = $newSettings['methodSrcRoot'];
		}
		
		return $newSettings;
	}
	
	public function listen(){
		
		//Get POST input
		$raw_input = file_get_contents('php://input');
		
		//Was any input sent? Don't complain if not, just show welcome.
		if ($raw_input==""){
			return new Response(200, "Welcome. Please specify a method.");
		}
		
		//Check content type
		if (!isset($_SERVER['CONTENT_TYPE'])){
			return new Response(400, "Bad Request: You must specify content-type.");
			
		//JSON	
		} else if ( $_SERVER['CONTENT_TYPE']!=="application/json" || $_SERVER['CONTENT_TYPE']!=="text/plain" ){
			
			$input = json_decode($raw_input, true);
			
		//All others	
		} else {
			return new Response(415);
		}
		
		//Check if input was decoded correctly
		if ($input===null){
			return new Response(400, "Bad Request: No data was sent or it was malformed.");
		}
		
		//Look for method
		if (!isset($input['method'])){
			return new Response(400, "Bad Request: Please specify a method.");
		}
		
		if (is_string($input['method']) && sizeof($input['method'])<100){
			$method = $input['method'];
		} else {
			return new Response(400, "Bad Request: The method you specified was malformed.");
		}
		
		//Look for meta
		if (isset($input['meta'])){
			$meta = $input['meta'];
		} else {
			$meta = null;
		}
		
		//Look for data
		if (isset($input['data'])){
			$data = $input['data'];
		} else {
			$data = null;
		}
	
		//Enforce meta
		$metaCheck = $this->_checkMeta($meta);
		if ($metaCheck!==true){
			return $metaCheck;
		}
		
		//Check signature
		$sigCheck = $this->_checkSignature($method, $meta, $data);
		if ($sigCheck!==true){
			return $sigCheck;
		}
		
		//Handle auth
		//todo
		
		//Make request
		return $this->request($method, $data);
		
	}
	
	protected function _checkMeta($meta){
		
		//Check nonce is unused
		//todo
		
		//Check request hasn't expired
		
		//Check data exists
		if (!isset($meta['valid']['from']) || !isset($meta['valid']['to'])){
			return new Response(400, "Bad Request: You must specify meta.valid.from and meta.valid.to");
		}

		//Check data
		if ($meta['valid']['from']>time()){
			return new Response(400, "Bad Request: meta.valid.from is in the future. Check your system time.");
		}
		if ($meta['valid']['to']<time()){
			return new Response(400, "Bad Request: meta.valid.to is in the past.".$meta['valid']['to']);
		}
		
		//Check signature exists
		if (!isset($meta['signature'])){
			return new Response(400, "Bad Request: This request has not been signed (meta.signature).");
		}
		
		return true;
	}
	
	protected function _checkSignature($method, $meta, $data){
		
		$oldKey = $meta['signature'];
		unset($meta['signature']);
		
		$text = json_encode([$method,$meta,$data]);
		$keyPlain = "swdapi";
		
		//Add user signature here
		
		//Hash the text
		$keyEnc = hash("sha256", $text.$keyPlain);
		
		if ($oldKey!==$keyEnc){
			return new Response(400, "Bad Request: The signature (meta.signature) didn't match.");
		}
		
		return true;
		
	}

}

class Response {
	 public $status;
	 public $data = null;
	 
	 public function __construct($status=200, $data=null) {
		 $this->status = $status;
		 $this->data = $data;
		 
		 if ($status===404 && $data===null){
		 	$this->data = "Requested method was not found.";
		 }
		 
		 if ($status===403 && $data===null){
		 	$this->data = "Access to the requested resource was denied.";
		 }
		 
		 if ($status===415 && $data===null){
		 	$this->data = "Unsupported Media Type";
		 }		 
	 }
	 
	 public function sendHttpResponse(){
	 	http_response_code($this->status);
	 	
	 	//Json
	 	if (is_array($this->data)){
	 		header('Content-Type: application/json');
	 		print json_encode($this->data);
	 		
	 	//Plain text
	 	} else {
	 		header('Content-Type: text/plain');
	 		print $this->data;
	 	}
	 }
}