<?php

namespace JamesSwift\SWDAPI;

require_once __DIR__."/submodules/PHPBootstrap/PHPBootstrap.php";

class SWDAPI extends \JamesSwift\PHPBootstrap\PHPBootstrap {
	
	protected $settings;
	protected $methods;
	protected $_securityFallback;
	protected $_credentialVerifier;
	protected $_db;
	protected $_predefinedMethods;
	
	//////////////////////////////////////////////////////////////////////
	// Methods required by PHPBootstrap
	
	public function loadDefaultConfig(){
		$this->settings = [];
		$this->methods = [];
		$this->_securityFallback = null;
		$this->_predefinedMethods = [
		"swdapi/registerClient"=> [
			"call"=>[$this, "_registerClient"]
		],
		"swdapi/getAuthToken"=> [
			"call"=>[$this, "_getAuthToken"]
		],
		
	];
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
	
	
	public function registerSecurityFallback(callable $callback){
		$this->_securityFallback = $callback;
	}
	
	public function registerCredentialVerifier(callable $callback){
		$this->_credentialVerifier = $callback;
	}
	
	public function getConfig(){
		return [$this->settings, $this->methods];
	}
	
	protected function _connectDB(){
		
		//Are we already connected
		if ($this->_db instanceof \PDO){
			return true;
		}
		
		//Are the settings loaded?
		if (!isset($this->settings['db']['user']) || !isset($this->settings['db']['pass']) || !isset($this->settings['db']['dsn'])){
			throw new \Exception("DB connection error. One or more required setting is missing.");
		}
		
		//Attempt to connect
		
		$this->_db  = new \PDO(
			$this->settings['db']['dsn'], 
			$this->settings['db']['user'], 
			$this->settings['db']['pass'], 
			[
			    \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
			    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
			    \PDO::ATTR_EMULATE_PREPARES   => false,
			]
		);
		return true;
	}
		
	public function request($methodID, $data=null, $authInfo=null){

		//Look in api predefined methods
		if (isset($this->_predefinedMethods[$methodID])){
			$method = $this->_predefinedMethods[$methodID];
		
		//Look in user defined methods	
		} else if (isset($this->methods[$methodID])){
			$method = $this->methods[$methodID];
			
		//Return 404
		} else {
			return new Response(404, ["SWDAPI-Error"=>[
					"code"=>404001,
					"message"=>"The method you requested could not be found."
				]]);
		}
		
		//Was $authInfo passed?
		if ($authInfo===null){
			
			//Try fallback security
			if (is_callable($this->_securityFallback)){
				$authInfo = call_user_func($this->_securityFallback);
			}
		}
		
		//Does this method require an authorized user?
		if (isset($method['requireAuthorizedUser']) && $method['requireAuthorizedUser']===true){
			
			if (!isset($authInfo['authorizedUser']) || $authInfo['authorizedUser']==null ){
				return new Response(403, ["SWDAPI-Error"=>[
					"code"=>403001,
					"message"=>"The method you requested requires authentication."
				]]);
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
		return call_user_func($method['call'], $data, $authInfo);

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
		
		//db
		if (isset($settings['db'])){
			if (isset($settings['db']['user']) && (!is_string($settings['db']['user']) || preg_match("$[^0-9a-zA-Z\-\\/\.]$", $settings['db']['user']))){
				throw new \Exception("The settings.db.user value is undefined or is an invalid format.");
			}
			if (isset($settings['db']['pass']) && !is_string($settings['db']['pass']) ){
				throw new \Exception("The settings.db.pass value is undefined or is an invalid format.");
			}
			if (isset($settings['db']['dsn']) && !is_string($settings['db']['dsn']) ){
				throw new \Exception("The settings.db.dsn value is undefined or is an invalid format.");
			}
			
			$newSettings['db'] = [
				"dsn"=>$settings['db']['dsn'],
				"user"=>$settings['db']['user'],
				"pass"=>$settings['db']['pass'],
			];
			
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
			return new Response(400, ["SWDAPI-Error"=>[
				"code"=>400001,
				"message"=>"Bad Request: You must specify a content-type header."
			]]);
			
		//JSON	
		} else if ( $_SERVER['CONTENT_TYPE']!=="application/json" || $_SERVER['CONTENT_TYPE']!=="text/plain" ){
			
			$input = json_decode($raw_input, true);
			
		//All others	
		} else {
			return new Response(415, ["SWDAPI-Error"=>[
				"code"=>415001,
				"message"=>"The content-type you requested is not supported."
			]]);
		}
		
		//Check if input was decoded correctly
		if ($input===null){
			return new Response(400, ["SWDAPI-Error"=>[
				"code"=>400002,
				"message"=>"Bad Request: No data was sent or it was malformed."
			]]);
		}
		
		//Look for method
		if (!isset($input['method'])){
			return new Response(400, ["SWDAPI-Error"=>[
				"code"=>400003,
				"message"=>"Bad Request: Please specify a method."
			]]);
		}
		
		if (is_string($input['method']) && strlen($input['method'])<100){
			$method = $input['method'];
		} else {
			return new Response(400, ["SWDAPI-Error"=>[
				"code"=>400004,
				"message"=>"Bad Request: The method you specified was malformed."
			]]);
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
		$response = $this->request($method, $data);
		
		if (!($response instanceof Response)){
			return new Response(500, ["SWDAPI-Error"=>[
				"code"=>500001,
				"message"=>"Internal server error: The method your requested returned an invalid datatype."
			]]);
		}
		
		return $response;
		
	}
	
	protected function _checkMeta($meta){

		//Check exipry data exists
		if (!isset($meta['valid']['from']) || !is_int($meta['valid']['from']) || !isset($meta['valid']['to']) || !is_int($meta['valid']['to'])){
			return new Response(400, ["SWDAPI-Error"=>[
				"code"=>400005,
				"message"=>"Bad Request: You must specify valid meta.valid.from and meta.valid.to values (integers)."
			]]);
		}

		//Check expiry data
		if ($meta['valid']['from']>time()){
			return new Response(400, ["SWDAPI-Error"=>[
				"code"=>400006,
				"message"=>"Bad Request: meta.valid.from is in the future. Check your system time."
			]]);
		}
		if ($meta['valid']['from']<time()-(60*2)){
			return new Response(400, ["SWDAPI-Error"=>[
				"code"=>400007,
				"message"=>"Bad Request: meta.valid.from is too far in the past. Check your system time."
			]]);
		}
		if ($meta['valid']['to']<time()){
			return new Response(400, ["SWDAPI-Error"=>[
				"code"=>400008,
				"message"=>"Bad Request: meta.valid.to is in the past. Check your system time."
			]]);
		}
		if ($meta['valid']['to']>time()+(60*2)){
			return new Response(400, ["SWDAPI-Error"=>[
				"code"=>400009,
				"message"=>"Bad Request: meta.valid.to is too far in the future. Check your system time."
			]]);
		}
		
		
		//Check nonce exists
		if (!isset($meta['nonce'])){
			return new Response(400, ["SWDAPI-Error"=>[
				"code"=>400010,
				"message"=>"Bad Request: You must specify a meta.nonce"
			]]);
		}
		
		//Check nonce format
		if (strlen($meta['nonce'])!=10 || preg_match("/[^0-9a-zA-Z]/", $meta['nonce'])!==0){
			return new Response(400, ["SWDAPI-Error"=>[
				"code"=>400011,
				"message"=>"Bad Request: The nonce you specified is an invalid format."
			]]);
		}
		
		//Check nonce is unique (by attempting to store it)
		if ($this->_registerNonce($meta['nonce'], $meta['valid']['to'])===false){
			return new Response(400, ["SWDAPI-Error"=>[
				"code"=>400012,
				"message"=>"Bad Request: The nonce you specified has already been used."
			]]);
		}
		
		//Check signature exists
		if (!isset($meta['signature'])){
			return new Response(400, ["SWDAPI-Error"=>[
				"code"=>400013,
				"message"=>"Bad Request: This request has not been signed (meta.signature)."
			]]);
		}
		
		return true;
	}
	
	protected function _registerNonce($nonce, $expires){
		
		//Make sure qe are connected to DB
		$this->_connectDB();
		
		//Clear old nonce
		$q = $this->_db->prepare("DELETE FROM nonce WHERE expires < :timestamp");
		$q->execute(["timestamp"=>time()]);
		
		//Attempt to insert nonce
		try {
			
			$q = $this->_db->prepare("INSERT INTO nonce SET value=:nonce, expires=:timestamp");
			$q->execute(["nonce"=>$nonce, "timestamp"=>$expires]);	
			return true;
			
		} catch (\PDOException $e){
			//If the error is a duplicate key constraint violation return false 
			if ($e->getCode() == 1062) {
		        return false;
		        
		    //If some other error, throw it again
		    } else {
		        throw $e;
		    }	
		}
	}
	
	protected function _getClientData($id){
		
		//Make sure qe are connected to DB
		$this->_connectDB();
		
		//Attempt to fetch id
		$q = $this->_db->prepare("SELECT * FROM clients WHERE id=:id");
		$q->execute(["id"=>$id]);
		$row = $q->fetch(\PDO::FETCH_ASSOC);
		
		if (is_array($row)){
			return $row;
		}
		
		throw new \Exception("Client does not exist.");
			
	}
	
	protected function _checkSignature($method, $meta, $data){
		
		$oldKey = $meta['signature'];
		unset($meta['signature']);
		
		//Reconstruct the signature from our end
		$text = json_encode([$method,$meta,$data], JSON_UNESCAPED_SLASHES);
		$keyPlain = "swdapi";
		
		//Add the client signature if sent
		if (isset($meta['client']['id'])){
			
			try {
				//Fetch key
				$clientData = $this->_getClientData($meta['client']['id']);
				//Add it to the hash
				$keyPlain.=$clientData['id'].$clientData['secret'];
			} catch (\Exception $e){
				
				return new Response(403, ["SWDAPI-Error"=>[
					"code"=>403002,
					"message"=>"The meta.client.id you specified doesn't exist."
				]]);
			
			}	
			
		}
		
		//Add authentication token if sent
		if (isset($meta['token']['id']) && isset($meta['token']['uid'])){
			
			try {
				//Fetch key
				$tokenData = $this->_getTokenData($meta['token']['uid'], $meta['token']['id']);
				//Add it to the hash
				$keyPlain.=$tokenData['secret'];
			} catch (\Exception $e){
				
				return new Response(403, ["SWDAPI-Error"=>[
					"code"=>403002,
					"message"=>"The meta.client.id you specified doesn't exist."
				]]);
			
			}	
			
		}
		
		//Hash the text
		$keyEnc = hash("sha256", $text.$keyPlain);
		
		if ($oldKey!==$keyEnc){
			return new Response(400, ["SWDAPI-Error"=>[
				"code"=>400014,
				"message"=>"Bad Request: The signature (meta.signature) didn't match."
			]]);
		}
		
		return true;
		
	}
	
	///////////////////////////////////////////
	//Predefined methods
	
	protected function _registerClient($data, $authInfo){
		
		//Make sure qe are connected to DB
		$this->_connectDB();
			
		$clientData = null;
		
		//BY default, assume we want to create a new client
		$action = "create-new";
		
		//Validate data
		if (!isset($data['name']) || !isset($data['salt'])){
			return new Response(403, ["SWDAPI-Error"=>[
				"code"=>403003,
				"message"=>"Bad Request: Data sent to swdapi/registerClient was malformed or incomplete."
			]]);
		}
		
		//See they already have a valid client id
		if (isset($data['id'])){
			try {
				
				//Fetch our copy including the pre-shared client secret
				$clientData = $this->_getClientData($data['id']);
				
				//Did they try to sign the request?
				if (isset($data['signature'])){
					
					//Recreate the signature and compare it
					if (hash("sha256", "swdapi".$clientData['id'].$clientData['secret'])===$data['signature']){

						//The signature matches, so they must have the secret too
						$action = "log-name";
					
					} else {

						//Their data is corrupt somehow or they are trying to guess the secret
						return new Response(403, ["SWDAPI-Error"=>[
							"code"=>403004,
							"message"=>"Bad Request: The client signature/hash you provided doesn't match. You client id-secret is corrupt in some way."
						]]);
						
					}
				//No signature, send them a new id-secret as they have obviously lost their secret
				} else {
					$action="create-new";
				}
				
			//No id or thier id has expired. Send them a new id-secret
			} catch (\Exception $e){
				$action="create-new";
			}
		}
		
		$name = substr($data['name'],0,140);
		$response = [];
		
		//Log the name and return name and id
		if ($action==="log-name"){

			try {
				//Attempt to store new name
				$q = $this->_db->prepare("UPDATE clients SET name=:name WHERE id=:id");
				$q->execute([
						"name"=>$name,
						"id"=>$data['id']
				]);
				
				//Create response
				$response['name'] = $name;
				$response['id'] = $clientData['id'];
				$response['signature'] = hash("sha256", "swdapi".$data['salt'].$clientData['id'].$clientData['secret']);
				
			} catch (\Exception $e){
				return new Response(500, ["SWDAPI-Error"=>[
					"code"=>500002,
					"message"=>"Internal server error: Error storing new client name."
				]]);
			}
			
		}
		
		if ($action==="create-new"){
			
			//Sanitize the data
			$secret = hash("sha256", "swdapi".mt_rand().$data['salt'].mt_rand().mt_rand());

			try {
				//Attempt to create new client
				$q = $this->_db->prepare("INSERT INTO clients SET name=:name, secret=:secret");
				$r = $q->execute([
						"name"=>$name,
						"secret"=>$secret
				]);
				
				//Create response
				$response['name'] = $name;
				$response['id'] = $this->_db->lastInsertId();
				$response['secret'] = $secret;
				
				//Create signature
				$response['signature'] = hash("sha256", "swdapi".$data['salt'].$clientData['id'].$clientData['secret']);
				
			 } catch (\Exception $e){
				return new Response(500, ["SWDAPI-Error"=>[
					"code"=>500003,
					"message"=>"Internal server error: Error creating new client."
				]]);
			}
			
		}
		
		//Return the data
		return new Response(200, $response);
	}
	
	
	
	protected function _getAuthToken($data, $authInfo){
		
		//Set defaults
		$expiry = time()+(60*60*24*2);
		$timeout = (60*10);
		
		//Define limits
		$maxExpiry = time()+(60*60*24*28);
		$maxTimeout = (60*60*24);
		
		//Check we have something configured to verify the credentials
		if (!is_callable($this->_credentialVerifier)){
			return new Response(500, ["SWDAPI-Error"=>[
				"code"=>500004,
				"message"=>"Internal server error: The api has not been configured to allow login with credentials. This request cannot be processed."
			]]);
		}
		
		//Check user was specified
		if (!isset($data['user']) || !is_string($data['user']) || strlen($data['user'])<3){
			return new Response(400, ["SWDAPI-Error"=>[
				"code"=>400015,
				"message"=>"Bad request: To receive an AuthToken you must specify a user (string, length >= 3)."
			]]);
		}
		
		//Check pass was specified
		if (!isset($data['pass']) || !is_string($data['pass']) || strlen($data['pass'])<3){
			return new Response(400, ["SWDAPI-Error"=>[
				"code"=>400016,
				"message"=>"Bad request: To receive an AuthToken you must specify a password (string, length >= 5)."
			]]);
		}
		
		//Check requestExpiry is valid
		if (isset($data['requestExpiry']) && $data['requestExpiry']!==null){
			$expiry = $data['requestExpiry'];
			if (!is_int($data['requestExpiry']) || $data['requestExpiry']<time()+30 || $data['requestExpiry']<$maxExpiry){
				return new Response(400, ["SWDAPI-Error"=>[
					"code"=>400020,
					"message"=>"Bad request: requestExpiry is not defined or is invalid. It must be an timestamp between 30 seconds from now and ".$maxExpiry
				]]);
			}
		} else {
			$data['requestExpiry'] = null;
		}
		
		//Check requestTimeout was set and is valid
		if (isset($data['requestTimeout']) && $data['requestTimeout']!==null){
			$timeout = $data['requestTimeout'];
			if (!is_int($data['requestTimeout']) || $data['requestTimeout']<5 || $data['requestTimeout']<$maxTimeout){
				return new Response(400, ["SWDAPI-Error"=>[
					"code"=>400021,
					"message"=>"Bad request: requestTimeout is not defined or is invalid. It must be an integar between 5 and ".$maxTimeout
				]]);
			}
		} else {
			$data['requestTimeout'] = null;
		}
		
		//Check salt was specified
		if (!isset($data['salt']) || !is_string($data['salt']) || strlen($data['salt'])<5){
			return new Response(400, ["SWDAPI-Error"=>[
				"code"=>400017,
				"message"=>"Bad request: To receive an AuthToken you must specify a salt (string, length >= 5)."
			]]);
		}
		
		//Check client ID was specified
		if (!isset($data['clientID']) || !is_string($data['clientID']) || strlen($data['clientID'])>10){
			return new Response(400, ["SWDAPI-Error"=>[
				"code"=>400018,
				"message"=>"Bad request: To receive an AuthToken you must specify a clientID (string, length < 10)."
			]]);
		}
		
		//Check signature was specified
		if (!isset($data['signature']) || !is_string($data['signature']) || strlen($data['signature'])!==64){
			return new Response(400, ["SWDAPI-Error"=>[
				"code"=>400019,
				"message"=>"Bad request: To receive an AuthToken you must specify a signature (string, length = 64)."
			]]);
		}
		
		//Check clientID is valid
		try {
			$clientData = $this->_getClientData($data['clientID']);
		} catch (\Exception $e){
			return new Response(403, ["SWDAPI-Error"=>[
				"code"=>403005,
				"message"=>"Forbidden: The client ID you specified doesn't exist"
			]]);
		}
			
		//Reconstruct the signature
		$newSig = hash("sha256", json_encode([
			$data['user'],
			$data['pass'],
			$data['requestExpiry'],
			$data['requestTimeout'],
			$data['salt'],
			$data['clientID'],
			$clientData['secret']
		]));
		
		//Compare the signature to our signature
		if ($newSig!==$data['signature']){
			return new Response(403, ["SWDAPI-Error"=>[
				"code"=>403006,
				"message"=>"Forbidden: The signature you sent doesn't match the data you sent and your clientID-secret."
			]]);	
		}
		
		//Check credentials
		$userDetails = call_user_func($this->_credentialVerifier, $data['user'], $data['pass']);
		if ($userDetails===false || !is_array($userDetails) || !isset($userDetails['authorizedUser']) || !is_string($userDetails['authorizedUser'])){
			return new Response(403, ["SWDAPI-Error"=>[
				"code"=>403007,
				"message"=>"Forbidden: The user or password you specified is wrong."
			]]);			
		}
		
		//Register a token (secret and id)
		try {
			$token = $this->_createAuthToken($userDetails['authorizedUser'], $expiry, $timeout);
		} catch (\Exception $e){
			return new Response(500, ["SWDAPI-Error"=>[
				"code"=>500005,
				"message"=>"Server Error: Could not create token in DB."
			]]);
		}
		
		//Create a singature of it
		$signature = hash("sha256", json_encode([$token, $data['salt'], $clientData['secret']]));
		
		//Return it
		return new Response(200, ['token'=>$token, 'signature'=>$signature]);
	}

}

class Response {
	 public $status;
	 public $data = null;
	 
	 public function __construct($status=200, $data=null) {
		 $this->status = $status;
		 $this->data = $data;
	 }
	 
	 public function sendHttpResponse(){
	 	http_response_code($this->status);
	 	
	 	//Json
	 	if (is_array($this->data)){
	 		header('Content-Type: application/json');
	 		print json_encode($this->data, JSON_UNESCAPED_SLASHES);
	 		
	 	//Plain text
	 	} else {
	 		header('Content-Type: text/plain');
	 		print $this->data;
	 	}
	 }
}