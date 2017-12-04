<?php

namespace JamesSwift\SWDAPI;

require_once __DIR__."/submodules/PHPBootstrap/PHPBootstrap.php";

class Server extends \JamesSwift\PHPBootstrap\PHPBootstrap {
	
	protected $settings;
	protected $methods;
	protected $_securityFallback;
	protected $_credentialVerifier;
	public $DB;
	protected $_predefinedMethods;
	
	//////////////////////////////////////////////////////////////////////
	// Methods required by PHPBootstrap
	
	public function loadDefaultConfig(){
		$this->settings = [];
		$this->methods = [];
		$this->_securityFallback = null;
		$this->_predefinedMethods = [
		"swdapi/registerClient"=> [
			"call"=>[$this, "_pdm__registerClient"]
		],
		"swdapi/getAuthToken"=> [
			"call"=>[$this, "_pdm__getAuthToken"]
		],
		"swdapi/invalidateAuthToken"=> [
			"call"=>[$this, "_pdm__invalidateAuthToken"],
			"requireAuthorizedUser" => true
		],
		"swdapi/validateAuthToken"=> [
			"call"=>[$this, "_pdm__validateAuthToken"],
			"requireAuthorizedUser" => true
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
	
	public function connectDB(){
		
		//Are we already connected
		if ($this->DB instanceof \PDO){
			return true;
		}
		
		//Are the settings loaded?
		if (!isset($this->settings['db']['user']) || !isset($this->settings['db']['pass']) || !isset($this->settings['db']['dsn'])){
			throw new \Exception("DB connection error. One or more required setting is missing.");
		}
		
		//Attempt to connect
		
		$this->DB  = new \PDO(
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
			
			if (!isset($authInfo['authorizedUser']) || !is_a($authInfo['authorizedUser'], "\JamesSwift\SWDAPI\Credential") && !is_string($authInfo['authorizedUser']->id) ){
				return new Response(403, ["SWDAPI-Error"=>[
					"code"=>403001,
					"message"=>"The method you requested requires authentication."
				]]);
			}
			
		}
		
		//Require method src file
		if (isset($method['src']) && is_array($method['src']) && sizeof($method['src'])>0 ){
			if (isset($this->settings['methodSrcRoot'])){
				foreach ($method['src'] as $src){
					require_once($this->settings['methodSrcRoot'].$src);
				}
			} else {
				foreach ($method['src'] as $src){
					require_once($src);
				}
			}
		}
		
		//Attempt to call method
		try {
			
			$response = call_user_func($method['call'], $data, $authInfo);
		
		//Listen for thrown responses 
		} catch (Response $ex){
			$response = $ex;
		}
		
		//Check that we have been give the correct object
		if (!$response instanceof Response){
			return new Response(500, ["SWDAPI-Error"=>[
					"code"=>500007,
					"message"=>"The method you requested didn't return a Response object."
				]]);
		}
		
		//Return the correct response
		return $response;

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
				throw new \Exception("A method's 'requireAuthorizedUser' definition must be type boolean if it exists.");
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

		//methodSrcRoot - you can optionaly specify the root path that method['src'] will attempt to resolve from
		if (isset($settings['methodSrcRoot'])){
			$newSettings['methodSrcRoot'] = $this->sanitizeFilePath($settings['methodSrcRoot'], false, true);
			
			//load it straight away, as other sanitization filters rely on this
			$this->settings['methodSrcRoot'] = $newSettings['methodSrcRoot'];
		}
		
		//Database
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
		
		//Tokens
		if (isset($settings['tokens'])){
			
			//Init array
			$newSettings['tokens'] = [];
			
			//maxExpiry
			if (isset($settings['tokens']['maxExpiry'])){
				
				if (!is_int($settings['tokens']['maxExpiry']) || $settings['tokens']['maxExpiry']<1){
					throw new \Exception("The settings.tokens.maxExpiry value must be a positive integar showing the maximum time in seconds from creation until the token expires.");
				}
				
				$newSettings['tokens']['maxExpiry'] = $settings['tokens']['maxExpiry'];
			}
			
			//defaultExpiry
			if (isset($settings['tokens']['defaultExpiry'])){
				
				if (!is_int($settings['tokens']['defaultExpiry']) || $settings['tokens']['defaultExpiry']<1){
					throw new \Exception("The settings.tokens.defaultExpiry value must be a positive integar showing the default time in seconds from creation until the token expires.");
				}
				
				$newSettings['tokens']['defaultExpiry'] = $settings['tokens']['defaultExpiry'];
			}
			
			//maxTimeout
			if (isset($settings['tokens']['maxTimeout'])){
				
				if (!is_int($settings['tokens']['maxTimeout']) || $settings['tokens']['maxTimeout']<1){
					throw new \Exception("The settings.tokens.maxTimeout value must be a positive integar showing the maximum time in seconds from creation until the token times-out with dis-use.");
				}
				
				$newSettings['tokens']['maxTimeout'] = $settings['tokens']['maxTimeout'];
			}
			
			//defaultTimeout
			if (isset($settings['tokens']['defaultTimeout'])){
				
				if (!is_int($settings['tokens']['defaultTimeout']) || $settings['tokens']['defaultTimeout']<1){
					throw new \Exception("The settings.tokens.defaultTimeout value must be a positive integar showing the default time in seconds from creation until the token times out with dis-use.");
				}
				
				$newSettings['tokens']['defaultTimeout'] = $settings['tokens']['defaultTimeout'];
			}
			
			
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
	
		//Enforce general meta
		$metaCheck = $this->_checkMeta($meta);
		if ($metaCheck!==true){
			return $metaCheck;
		}
		
		//Check signature (including token authentication)
		$sigCheck = $this->_checkSignature($method, $meta, $data);
		if ($sigCheck!==true){
			return $sigCheck;
		}
		
		//Find permissions
		$permissions = null;
		if (isset($meta['token'])){
			$token = $this->_fetchAuthToken($meta['token']['id'], $meta['token']['uid']);
			$permissions = $token['permissions'];
		}
		
		
		//Handle auth
		$auth=null;
		if (isset($meta['token']['uid']) && is_string($meta['token']['uid'])){
			$auth = ['authorizedUser'=> new \JamesSwift\SWDAPI\Credential($meta['token']['uid'], $permissions)];
		}
		
		//Make request
		$response = $this->request($method, $data, $auth);
		
		if (!($response instanceof Response)){
			return new Response(500, ["SWDAPI-Error"=>[
				"code"=>500001,
				"message"=>"Internal server error: The method you requested returned an invalid datatype."
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
		$this->connectDB();
		
		//Clear old nonce
		$q = $this->DB->prepare("DELETE FROM nonce WHERE expires < :timestamp");
		$q->execute(["timestamp"=>time()]);
		
		//Attempt to insert nonce
		try {
			
			$q = $this->DB->prepare("INSERT INTO nonce SET value=:nonce, expires=:timestamp");
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
		$this->connectDB();
		
		//Attempt to fetch id
		$q = $this->DB->prepare("SELECT * FROM clients WHERE id=:id");
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
		$text = json_encode([$method,$meta,$data], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
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
		if (isset($meta['token'])){
			
			if (!isset($meta['token']['id']) || !is_int($meta['token']['id']) || !isset($meta['token']['uid']) || !is_string($meta['token']['uid'])){
				return new Response(403, ["SWDAPI-Error"=>[
					"code"=>403015,
					"message"=>"The meta.token you specified is an invalid format."
				]]);				
			}
			
			try {
				//Fetch token
				$tokenData = $this->_fetchAuthToken($meta['token']['id'], $meta['token']['uid']);
				
				//Check this token is allowed on this client
				if ($tokenData['clientID']!==$clientData['id']){
					return new Response(403, ["SWDAPI-Error"=>[
						"code"=>403009,
						"message"=>"The meta.token you specified is not allowed to be used by your client/terminal."
					]]);
				}
				
				//Check token hasn't expired
				if ($tokenData['expires']<=time()){
					return new Response(403, ["SWDAPI-Error"=>[
						"code"=>403010,
						"message"=>"The meta.token you specified has expired."
					]]);
				}
				
				//Check the uid sent is the uid that is authorized
				if ($meta['token']['uid']!==$tokenData['uid']){
					return new Response(403, ["SWDAPI-Error"=>[
						"code"=>403012,
						"message"=>"The meta.token.uid you specified is invalid."
					]]);
				}
				
				//Check timeout
				if ($tokenData['lastUsed']+$tokenData['timeout']<=time()){
					return new Response(403, ["SWDAPI-Error"=>[
						"code"=>403011,
						"message"=>"The meta.token you specified has timed-out.".time()
					]]);
				}

				//Add it to the hash
				$keyPlain.=$tokenData['id'].$tokenData['secret'];
				
			} catch (\Exception $e){
				
				return new Response(403, ["SWDAPI-Error"=>[
					"code"=>403008,
					"message"=>"The meta.token you specified doesn't exist or has timed out."
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
	
	protected function _createAuthToken($userID, $clientID, $permissions, $expires, $timeout){
		
		//Make sure qe are connected to DB
		$this->connectDB();
		
		//Build token
		$token = [
			"uid"=>$userID,
			"secret"=>hash("sha256", openssl_random_pseudo_bytes(200)),
			"permissions"=>json_encode($permissions, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
			"expires"=>$expires,
			"timeout"=>$timeout,
			"clientID"=>$clientID,
			"lastUsed"=>time()
		];
		
		//Attempt to add row
		$q = $this->DB->prepare("INSERT INTO tokens SET clientID=:clientID, uid=:uid, secret=:secret, permissions=:permissions, expires=:expires, timeout=:timeout, lastUsed=:lastUsed");
		$q->execute($token);
		$tokenID = (int)$this->DB->lastInsertId();
		
		//Build full token data and return it
		$token['type'] = "SWDAPI-AuthToken";
		$token['id']=$tokenID;
		unset($token['lastUsed']);
		$token['permissions']=$permissions;
		
		return $token;
			
	}
	
	protected function _fetchAuthToken($tokenID, $userID, $updateLastUsed=true){
		
		//Make sure we are connected to DB
		$this->connectDB();

		//Attempt to fetch row
		$q = $this->DB->prepare("SELECT * FROM tokens WHERE id=:tokenID AND uid=:userID");
		$q->execute(["tokenID"=>$tokenID, "userID"=>$userID]);
		
		$row = $q->fetch(\PDO::FETCH_ASSOC);
		
		if (is_array($row)){
			
			//Update the lastUsed?
			if ($updateLastUsed===true){
				
				//Has the token timed-out?
				if ($row['lastUsed']+$row['timeout']<=time()){
					throw new \Exception("Token has timed-out");
				}
				
				$q = $this->DB->prepare("UPDATE tokens SET lastUsed=:lastUsed WHERE id=:tokenID AND uid=:userID");
				$q->execute(["lastUsed"=>time(), "tokenID"=>$tokenID, "userID"=>$userID]);	
			}
			
			//Return the previous state
			return $row;
		}
		
		throw new \Exception("Token does not exist.");
			
	}
	
	protected function _invalidateAuthToken($tokenID){
		
		//Make sure qe are connected to DB
		$this->connectDB();

		//Attempt to delete row
		$q = $this->DB->prepare("DELETE FROM tokens WHERE id=:tokenID");
		$q->execute(["tokenID"=>$tokenID]);
		
		return true;	
	}
	
	///////////////////////////////////////////
	//Predefined methods
	
	protected function _pdm__registerClient($data, $authInfo){
		
		//Make sure qe are connected to DB
		$this->connectDB();
			
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
				$q = $this->DB->prepare("UPDATE clients SET name=:name WHERE id=:id");
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
				$q = $this->DB->prepare("INSERT INTO clients SET name=:name, secret=:secret");
				$r = $q->execute([
						"name"=>$name,
						"secret"=>$secret
				]);
				
				//Create response
				$response['name'] = $name;
				$response['id'] = $this->DB->lastInsertId();
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
	
	
	
	protected function _pdm__getAuthToken($data, $authInfo){
		
		//Set defaults
		$expiry = time()+(isset($this->settings['tokens']['defaultExpiry']) ? $this->settings['tokens']['defaultExpiry'] : 172800); //2 Days
		$timeout = (isset($this->settings['tokens']['defaultTimeout']) ? $this->settings['tokens']['defaultTimeout'] : 600); //10 minutes
		
		//Define limits
		$maxExpiry = time()+(isset($this->settings['tokens']['maxExpiry']) ? $this->settings['tokens']['maxExpiry'] : 2419200); //28 days
		$maxTimeout = (isset($this->settings['tokens']['maxTimeout']) ? $this->settings['tokens']['maxTimeout'] : 86400); //1 day
		
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
		
		//Check requestPermissions was specified
		if (isset($data['requestPermissions']) ){
			//No valid formats specified yet
		} else {
			$data['requestPermissions'] = null;
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
			$data['requestPermissions'],
			$data['requestExpiry'],
			$data['requestTimeout'],
			$data['salt'],
			$data['clientID'],
			$clientData['secret']
		], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) );
		
		//Compare the signature to our signature
		if ($newSig!==$data['signature']){
			return new Response(403, ["SWDAPI-Error"=>[
				"code"=>403006,
				"message"=>"Forbidden: The signature you sent doesn't match the data you sent and your clientID-secret."
			]]);	
		}
		
		//Check credentials
		$credentialResult = call_user_func($this->_credentialVerifier, $data['user'], $data['pass'], $data['requestPermissions'], $clientData);
		
		if ($credentialResult===false || !is_a($credentialResult, "\JamesSwift\SWDAPI\Credential") || is_string($credentialResult)->id){
			return new Response(403, ["SWDAPI-Error"=>[
				"code"=>403007,
				"message"=>"Forbidden: The user or password you specified is wrong."
			]]);			
		}
		
		//Register a token (secret and id)
		try {
			
			$token = $this->_createAuthToken($credentialResult->id, $clientData['id'], $credentialResult->permissions, $expiry, $timeout);
	
		} catch (\Exception $e){
			
			return new Response(500, ["SWDAPI-Error"=>[
				"code"=>500005,
				"message"=>"Server Error: Could not create token in DB."
			]]);
			
		}
		
		//Create a singature of it
		$signature = hash("sha256", json_encode([$token, $data['salt'], $clientData['secret']], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
		
		//Return it
		return new Response(200, ['token'=>$token, 'signature'=>$signature]);
	}
	
	protected function _pdm__invalidateAuthToken($data, $authInfo){
		
		//Check some id was specified
		if (!isset($data['id']) || !is_int($data['id'])){
			return new Response(400, ["SWDAPI-Error"=>[
				"code"=>400022,
				"message"=>"Bad request: You must specify the 'id' of the token you wish to invalidate."
			]]);
		}
		
		//Attempt to fetch token (to make sure we have access)
		try {
				//Fetch token
				$tokenData = $this->_fetchAuthToken($data['id'], $authInfo['authorizedUser']->id);
		
			
		} catch(\Exception $e){
			return new Response(403, ["SWDAPI-Error"=>[
				"code"=>403014,
				"message"=>"Access denied: The token you specified could not be found, or you don't have access to delete it."
			]]);
		}
		
		
		//Attempt to delete the token
		try {
			$this->_invalidateAuthToken($data['id']);
			
			return new Response(200, true);
			
		} catch(\Exception $e){
			return new Response(500, ["SWDAPI-Error"=>[
				"code"=>500006,
				"message"=>"Server Error: Problem removing the token from the DB."
			]]);
		}
		
	}
	
	protected function _pdm__validateAuthToken($data, $authInfo){
		return new Response(200, true);
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
		if (is_array($this->data) || is_bool($this->data)){
		 header('Content-Type: application/json');
		 print json_encode($this->data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		 
		//Plain text
		} else {
		 header('Content-Type: text/plain');
		 print $this->data;
		}
	}
}

class Credential {
	
	public $id;
	public $permissions = null;
	
	public function __construct($id, $permissions=null) {
		$this->id = (string)$id;
		$this->permissions = $permissions;
	}
	 
}