var swdapi = swdapi || {};

swdapi.client = swdapi.client || function(URI, config) {

	//Shims
	function defaultFor(arg, val) {
		return typeof arg !== 'undefined' ? arg : val;
	}
	
	//Date now support
	if (!Date.now) {
	    Date.now = function() { return new Date().getTime(); }
	}	

	config = defaultFor(config, {});

	///////////////////////////////
	// Begin constructor

	//Test that needed components exist
	if (typeof JSON === "undefined") {
		throw "SWDAPI: Required component 'JSON' not defined. Please include an external JSON library.";
	}	
	if (typeof XMLHttpRequest === "undefined") {
		throw "SWDAPI: Required component 'XMLHttpRequest' not defined.";
	}
	if (typeof jsSHA === "undefined") {
		throw "SWDAPI: Required component 'jsSHA' not defined. Did you forget to include it?";
	}	
	Number.isInteger = Number.isInteger || function(value) {
	    return typeof value === "number" &&
	           isFinite(value) &&
	           Math.floor(value) === value;
	};
	if (!Array.prototype.indexOf){
		Array.prototype.indexOf=function(b){var a=this.length>>>0;var c=Number(arguments[1])||0;c=(c<0)?Math.ceil(c):Math.floor(c);if(c<0){c+=a}for(;c<a;c++){if(c in this&&this[c]===b){return c}}return -1}
	}
	if (typeof window.console === "undefined"){
		window.console = {};
	}
	if (typeof console.log === "undefined") {
		console.log = function(){};
	}


	//Variable for this API instance
	var endpointURI = URI,
		serverTimeOffset = 0,
		defaultToken = null,
		defaultClientName,
		listeners = {"defaultToken": []},
		autoReregister = (typeof config['autoReregister'] === "boolean" ? config['autoReregister'] : true),
		fetchClientData = (typeof config['fetchClientData'] === "function" ? config['fetchClientData'] : fetchClientData_Default),
		storeClientData = (typeof config['storeClientData'] === "function" ? config['storeClientData'] : storeClientData_Default),

		//Defined the public object
		pub = {
			"login": login,
			"logout": logout,
			"getAuthToken": getAuthToken,
			"setDefaultToken": setDefaultToken,
			"validateAuthToken": validateAuthToken,
			"request": request,
			"serverDate": getServerDate,
			"registerClient": registerClient,
			"addListener": addListener,
			"removeListener": removeListener,
			"setFetchClientDataHandler": function(handler) {
				if (typeof handler === "function") {
					fetchClientData = handler;
					return true;
				}
				throw "Could not store fetchClientData handler. Not a callable function.";
			},
			"setStoreClientDataHandler": function(handler) {
				if (typeof handler === "function") {
					storeClientData = handler;
					return true;
				}
				throw "Could not store storeClientData handler. Not a callable function.";
			}
		};

	//Check if we should register and/or set the name of the client
	if (config.setClientName !== undefined && typeof config.setClientName === "string") {

		//Is it different from what we have stored?
		var tmpData = fetchClientData();
		if (typeof tmpData !== "object" || tmpData.name === undefined || tmpData.name !== config.setClientName.substring(0, 140) || tmpData.secret === undefined || tmpData.id === undefined) {
			//Register the new name
			registerClient(config.setClientName);
		}
		
	//Check if there is a default name for the client which we should use to register the client with
	} else if (config.defaultClientName !== undefined && typeof config.defaultClientName === "string") {
		
		defaultClientName = config.defaultClientName;
		
		//Is it different from what we have stored?
		var tmpData = fetchClientData();
		if (typeof tmpData !== "object" || tmpData.name === undefined ||  tmpData.secret === undefined || tmpData.id === undefined) {
			//Register the client
			registerClient();
		}
	}
	

	//If the serverTimestamp was specified use it
	if (config['serverTimestamp'] !== undefined) {

		//Check that the supplied timestamp is correct format
		if (Number.isInteger(config['serverTimestamp']) === true) {

			storeServerTimeOffset(config['serverTimestamp'] * 1000);

		}
		else {
			console.log("Supplied serverTimestamp was an invalid format. Must be positive integer. The server time will be noted on the next request instead.");
		}
	}


	//Return the public object
	return pub;

	// End constructor
	//////////////////////////////////////////



	//////////////////////////////////////////
	//Define private methods

	function generateMeta(method, data, token) {

		var meta = {},
			sT = getServerDate().getTime(),
			client = fetchClientData();

		//Client
		if (client.id !== undefined && client.secret !== undefined) {
			meta.client = {
				"id": client.id
			};
		}

		//User auth Token
		if (token !== null && token.id !== undefined && token.uid !== undefined) {
			meta.token = {
				"id": token.id,
				"uid": token.uid
			};
		}

		//Nonce
		meta.nonce = generateSalt();

		//Set expiry to be +/- 1 minute
		meta.valid = {
			"from": Math.floor(sT / 1000) - (60),
			"to": Math.floor(sT / 1000) + (60)
		};

		//Sign request
		meta.signature = signRequest(method, meta, data, token);

		return meta;
	}

	function signRequest(method, meta, data, token) {

		var text, keyPlain, keyEnc, client = fetchClientData();

		text = JSON.stringify([method, meta, data]);
		keyPlain = "swdapi";

		//Include client secret in hmac key if registered
		if (meta.client !== undefined && meta.client.id !== undefined && client.secret !== undefined) {
			keyPlain += client.id + client.secret;
		}

		//Include user key and pin in hmac key
		if (meta.token !== undefined && token.id !== undefined && token.secret !== undefined) {
			keyPlain += token.id + token.secret
		}
		
		//Join the dots and hash

		var sha = new jsSHA("SHA-256", "TEXT");
		sha.setHMACKey(keyPlain, "TEXT");
		sha.update(text);
		keyEnc = sha.getHMAC("HEX");

		return keyEnc;
	}


	//Aquire a new AuthToken and set it as defaultToken
	function login(user, pass, callback, requestPermissions) {

		callback = defaultFor(callback, null);

		getAuthToken(user, pass, function(token) {

			//If login successfull, store the token for future use
			if (token !== null && typeof token == "object" && "type" in token && token.type === "SWDAPI-AuthToken") {
				defaultToken = setDefaultToken(token);
			}

			//Execute callback whether login successfull or not
			if (typeof callback === "function") {
				callback(token);
			}

		}, requestPermissions);
	}

	//Set the defaultToken to null
	function logout(callback) {

		var handler;
		
		callback = defaultFor(callback, null);
		
		//Handle response
		handler = function(response) {
			if (response === true) {
				console.log("Logout completed");
				defaultToken = setDefaultToken(null);
			}
			else {
				console.log("Logout failed.");
			}
			if (typeof callback === "function") {
				callback(response);
			}
		};

		//Are we currently logged in?
		if (defaultToken !== null) {
			
			console.log("Requesting logout.")
			
			//Make logout request and call handler with response
			request("swdapi/invalidateAuthToken", {
				"id": defaultToken.id
			}, handler, handler, defaultToken);
			

		//Not logged in, just run callback with true
		}
		else {
			console.log("logout called when not logged in.")
			if (typeof callback === "function") {
				callback(false);
			}
			
		}
		
	}

	//Attempts to fetch user session token with supplied credentials
	//
	// requestExpiry is when you wish the token to definitely be invalidated (as a unix timestamp)
	// requestTimeout is the amount of seconds of inactivity you would like the server will invalidate the token after (in seconds from now)
	//
	// Your requested expiry and timeout may not be honoured by the server, always refer to the response
	//
	// On success it calls "callback" and passes an object [
	//  uid 	= string - the id of the user account this token is for
	//  clientID= int - the id of the client this token is linked to
	//	id		= int - the id of the session token
	//  secret	= string 64 - the secret key
	//  expiry	= int - unixtime that this token expires
	//  timeout = int - seconds of inactivity after which the token will expire
	// ]
	//
	// On Failure it calls "callback" and passes an SWDAPI-Error object
	//
	// Test like this:
	// if (reponse['SWDAPI-Error']===undefined){ //all good
	

	function getAuthToken(user, pass, callback, requestPermissions, requestExpiry, requestTimeout) {

		var clientData, data, successHandler, failureHandler;
		
		callback = defaultFor(callback, null);
		requestPermissions = defaultFor(requestPermissions, null);
		requestExpiry = defaultFor(requestExpiry, null);
		requestTimeout = defaultFor(requestTimeout, null);

		//Validate user
		if (user===undefined || typeof user !== "string"){
			throw "The user ID you specified is invalid. It must be a non-empty string.";
		}
		//Check length
		if (user.length<3){
			throw "The user ID you specified is too short";
		}
		
		//Validate pass
		if (pass===undefined || typeof pass !== "string"){
			throw "The password you specified is invalid. It must be a non-empty string.";
		}
		//Check length
		if (pass.length<5){
			throw "The password you specified is too short";
		}
		
		//Validate exipry if specified
		if (requestExpiry!==null && (
				!(typeof requestExpiry === "number" && (requestExpiry % 1)===0) || requestExpiry<=getServerDate().now()
			 )
		){
			throw "The expiry you requested for your user session is not a number or is in the past.";
		}
		
		//Validate timeout if specified
		if (requestTimeout!==null && ( !(typeof requestExpiry === "number" && (requestExpiry % 1)===0) || requestTimeout<=1)){
			throw "The requestTimeout you requested for your user session is not a number or is less than one.";
		}		
		
		//Check we have registered a client
		clientData = fetchClientData();
		
		if (typeof clientData !== "object" || clientData.id === undefined  || typeof clientData.id !== "string" || clientData.secret === undefined || typeof clientData.secret !== "string" ) {
			throw "You cannot request an AuthToken without having a valid client to assign the token to.";
		}
		
		//Build data to send
		var salt=generateSalt();
		data = {
			"user":user,
			"pass":pass,
			"clientID":clientData.id,
			"requestPermissions":requestPermissions,
			"requestExpiry":requestExpiry,
			"requestTimeout":requestTimeout,
			"salt":salt
		};
		
		//Sign it
		var sha = new jsSHA("SHA-256", "TEXT");
		sha.setHMACKey(clientData.secret, "TEXT");
		sha.update(JSON.stringify([
					user,
					pass,
					requestPermissions,
					requestExpiry,
					requestTimeout,
					salt,
					clientData.id,
		]));
		data.signature = sha.getHMAC("HEX");
		
		//define Successful request
		successHandler = function(response){
			
			//Recreate response signature
			var sha = new jsSHA("SHA-256", "TEXT");
			sha.setHMACKey(clientData.secret, "TEXT");
			sha.update(JSON.stringify([
					response.token,
					salt
			]));
			var ourSig = sha.getHMAC("HEX");
					
			//Check the signature matches (i.e., no man in the middle)
			if (ourSig!==response.signature){
				throw "Error in returned auth token. Signature is invalid.";
			}
			
			console.log("Successfully authenticated as "+user);
			
			//Execute callback
			if (typeof callback === "function") {
				callback(response.token);
			}
		};
		
		//define Problem with request
		failureHandler = function(response){
			
			console.log("Could not authenticate as "+user);
			
			if (typeof callback === "function") {
				callback(response);
			} else {
				throw response;
			}
		};
		
		//Make request to server
		request("swdapi/getAuthToken", data, successHandler, failureHandler, null);
		
	}
	
	function setDefaultToken(token){
		
		if (token === null || checkTokenFormat(token)){
			defaultToken = token;
			callListener("defaultToken", token);
			return token;
		}
		return false;
	}
	
	function validateAuthToken(token, callback){
		
		defaultFor(token, defaultToken);
		
		if (!checkTokenFormat(token)){
			callback(false);
			return false;
		}
		
		return request("swdapi/validateAuthToken", null, 
			function(){
				callback(true);
			},
			function(error){
				callback(error);
			}, token
		);
	}

	function checkTokenFormat(token) {

		//First, is there something to test?
		if (token===undefined || token === null || typeof token !== "object"){
			console.log("Invalid token: must be object");
			return false;
		}
		
		//Check for type string
		if (!("type" in token) || typeof token.type !== "string" || token.type !== "SWDAPI-AuthToken"){
			console.log("Invalid token: Doesn't contain valid type definition.");
			return false;
		}
		
		//Check for id
		if (token.id===undefined || Number.isInteger(token.id) === false || token.id < 0){
			console.log("Invalid token: id must be defined and be a non-zero integar");
			return false;
		}
		
		//Check for clientID
		if (token.clientID===undefined || Number.isInteger(token.clientID) === false || token.clientID < 0){
			console.log("Invalid token: clientID must be defined and be a non-zero integar");
			return false;
		}
		
		//Check for uid
		if (token.uid===undefined || typeof token.uid !== "string" || token.uid.length < 1){
			console.log("Invalid token: uid must be defined and be a non-empty string");
			return false;
		}
		
		//Check for secret
		if (token.secret===undefined || typeof token.secret !== "string" || token.secret.length !== 64){
			console.log("Invalid token: secret must be defined and be a string (64)");
			return false;
		}
		
		//Check for expires
		if (token.expires===undefined || Number.isInteger(token.expires) === false || token.expires < 1){
			console.log("Invalid token: expires must be defined and be a non-zero integar");
			return false;
		}
		
		//Check for timeout
		if (token.timeout===undefined || Number.isInteger(token.timeout) === false || token.timeout < 1){
			console.log("Invalid token: timeout must be defined and be a non-zero integar");
			return false;
		}
		
		//Other elements are allowed but are not referenced by the api
		
		return true;
	}

	//Set or change the name of the client/terminal/device/whatever
	//and get a unique id-sceret pair
	//which should be stored semi-permenantly
	function registerClient(name, callback) {

		name = defaultFor(name, null);
		callback = defaultFor(callback, null);

		var currentData = fetchClientData(),
			sendData = {
				"salt": generateSalt()
			},
			callbackHandler;

		//Set id if known
		if (typeof currentData === "object" && currentData.id !== undefined) {
			sendData.id = currentData.id;
		}

		//Hash/sign the id and secret (if known)
		if (typeof currentData === "object" && currentData.id !== undefined && currentData.secret !== undefined) {
			var sha = new jsSHA("SHA-256", "TEXT");
			sha.setHMACKey(currentData.secret, "TEXT");
			sha.update("swdapi" + currentData.id);
			sendData.signature = sha.getHMAC("HEX");
		}

		//check whether to use new name or old (if it exists)
		if (typeof name === "string") {
			sendData.name = name.substring(0, 140);
		}
		else if (name !== null) {
			throw "Cannot register client. Argument 1 must be either a string or null.";
		}
		else if (typeof currentData === "object" && currentData.name !== undefined) {
			//Use the name we already have registered
			sendData.name = currentData.name.substring(0, 140);
		}
		else if (typeof defaultClientName === "string"){
			//Use the default name specified in constructor
			sendData.name = defaultClientName.substring(0, 140);
		}
		else {
			throw "Cannot register a client without a name. No name is stored and no name was passed to registerClient()";
		}

		//////////////////
		//Define a callback handler which will accept data from the server and store it locally
		callbackHandler = function(responseData) {

			if (typeof responseData !== "object" || responseData.id === undefined || responseData.name === undefined) {
				throw "Could not confirm registration of the client. An unexpected error occured.";
			}

			var newClientData = {
					"name": responseData.name,
					"id": responseData.id
				},
				ourSig;

			//Did we attempt the request with a client signature?

			//Yes, so the server should have sent back a hash for us to compare and confirm it's identity
			if (currentData.id !== undefined && currentData.secret !== undefined) {
				//Don't forget to store the current secret again
				newClientData.secret = currentData.secret;
				
				var sha = new jsSHA("SHA-256", "TEXT");
				sha.setHMACKey(currentData.secret, "TEXT");
				sha.update("swdapi" + sendData.salt + sendData.id);
				ourSig = sha.getHMAC("HEX");
				
				if (responseData.signature === undefined || ourSig !== responseData.signature) {
					throw "Failed to confirm the client id. " +
						"The signature returned by the server doesn't match ours. " +
						"This should be impossible without a man in the middle.";
				}


			//No, so we should be getting a new secret back	
			} else if (responseData.secret !== undefined) {
				newClientData.secret = responseData.secret;
			} else {
				throw "Failed to register client: no secret returned by server.";
			}


			//Store the new state
			storeClientData(newClientData);
			console.log("New client registered successfully: " + newClientData.name);

			//Call the callback
			if (typeof callback === "function") {
				callback();
			}
		};
		///////////////////////

		//Make a request to register/confirm the client
		//Send what data we have even if incomplete
		//The server call will either return some data for us to 
		//store or if we have an expired secret it will return 403
		//(in which case we discard the old id-secret pair and request a new one)

		console.log("Registering client with server.");
		
		request("swdapi/registerClient", sendData,

			//Success handler
			callbackHandler,

			//Failure handler
			function(responseData) {
				//Check if this request failed because our meta.signature was invalid (or id not found)
				if (
						typeof responseData !== "object" || 
						responseData['SWDAPI-Error'] === undefined || 
						[400014, 403002, 403004].indexOf(parseInt(responseData['SWDAPI-Error'].code)) === -1
				){
					throw "Could not register the client. An unexpected error occured.";
				}

				//So, we only reach this point if the client id-secret pair was invalid (expired or corrupted)
				console.log("The client id-secret pair stored on this device has either expired or is corrupt. Requesting a new one.");

				//Remove the invalid client id-secret pair identity and store it
				delete currentData.id;
				delete currentData.secret;
				storeClientData({
					"name": sendData.name
				});
				
				//Rewrite send data to remove corrupt info
				sendData = {
					name: sendData.name,
					salt: generateSalt()
				};

				//Run a new request just specifying a client name and a salt value
				//which will return a new id-secret pair for us to use
				request("swdapi/registerClient", sendData, callbackHandler,

					//If it still fails, just throw an error and give up
					function() {
						throw "Could not register the client. A very unexpected error occured.";
					}

				);
			}
		);


	}

	function fetchClientData_Default() {
		if (!storageAvailable("localStorage")) {
			throw "Unable to access localStorage API. You must define your own fetchClientData and storeClientData handlers.";
		}
		var client = {};
		if (window.localStorage.getItem("client-id") !== null) {
			client.id = window.localStorage.getItem("client-id");
		}
		if (window.localStorage.getItem("client-secret") !== null) {
			client.secret = window.localStorage.getItem("client-secret");
		}
		if (window.localStorage.getItem("client-name") !== null) {
			client.name = window.localStorage.getItem("client-name");
		}

		return client;
	}

	function storeClientData_Default(data) {
		if (!storageAvailable("localStorage")) {
			throw "Unable to access localStorage API. You must define your own fetchClientData and storeClientData handlers.";
		}
		if (data.id !== undefined) {
			window.localStorage.setItem("client-id", data.id);
		}
		else {
			window.localStorage.removeItem("client-id");
		}
		if (data.secret !== undefined) {
			window.localStorage.setItem("client-secret", data.secret);
		}
		else {
			window.localStorage.removeItem("client-secret");
		}
		if (data.name !== undefined) {
			window.localStorage.setItem("client-name", data.name);
		}
		else {
			window.localStorage.removeItem("client-name");
		}
		return true;
	}

	function storageAvailable(type) {
		try {
			var storage = window[type],
				x = '__storage_test__';
			storage.setItem(x, x);
			storage.removeItem(x);
			return true;
		}
		catch (e) {
			return false;
		}
	}

	function request(method, data, successCallback, failureCallback, token) {

		token = defaultFor(token, defaultToken);
		successCallback = defaultFor(successCallback, null);
		failureCallback = defaultFor(failureCallback, null);

		var xmlhttp = new XMLHttpRequest(),
			meta = null,
			ttl = 5;

		//Validate "token" object
		if (token!==null && checkTokenFormat(token)!==true) {
			throw "The authentication token you passed is in an invalid format. Request not sent.";
		}

		//Generate meta for this request (and sign it)
		meta = generateMeta(method, data, token);

		xmlhttp.open("POST", endpointURI);
		xmlhttp.setRequestHeader("Content-Type", "application/json;charset=UTF-8");

		//Response handler
		xmlhttp.onreadystatechange = function() {

			//Check that loading is complete
			if (xmlhttp.readyState !== xmlhttp.DONE) {
				return;
			}

			//Decode json response automatically
			var response = (xmlhttp.getResponseHeader('content-type') === "application/json" ? JSON.parse(xmlhttp.responseText) : xmlhttp.responseText);

			//OK - route response to callback
			if (xmlhttp.status === 200) {
				if (typeof successCallback === "function") {
					successCallback(response, xmlhttp.status, method, data, (token === null ? null : token.id) );
				}
				return true;

			}

			//Handle non-200 response codes

			//Decrease TTL
			ttl -= 1;
			
			if (ttl > 0 ){
				//Is this a SWDAPI error
				if (typeof response !== "string" && response['SWDAPI-Error'] !== undefined && response['SWDAPI-Error']['code'] !== undefined && method !== "swdapi/validateAuthToken") {
	
					var code = response['SWDAPI-Error']['code'];
	
					//Is it one we want to recover from?
	
					//valid.from & valid.to errors
					if (code >= 400006 && code <= 400009) {
	
						console.log("Request failed due to expiry data. Reseting system time offset and trying again.");
	
						//Reset serverTimeOffset with date supplied by this request
						storeServerTimeOffset(xmlhttp.getResponseHeader("date"));
	
					//Invalid client id or failed signature
					}
					else if (code == 400014 || code == 403002) {
						
						//Warn the developer (as changing client id automatically would de-authorize all connected accounts)
						console.log(
							"SWDAPI: An api request to '"+method+"' failed because the server reported that the request's signature was invalid. " +
							"This could be because the client id-secret pair you are using is invalid or has expired, the authentication token you are using has expired or is invalid, or some other secret information embeded in the signature has changed on the server. "
						);
						
						//Don't auto re-run this request, as resetting client data automatically invalidates the auth token
						ttl = 0;
						
						//Should we attempt to recover?
						if (autoReregister===true && method !== "swdapi/registerClient"){
							console.log("Attemping to automatically re-register the client using the original name.")
							
							//Clear out all but the name from the Client Data
							var tempData = fetchClientData();
							storeClientData({
								"name": tempData.name
							});
							
							//Remove the default token
							defaultToken = setDefaultToken(null);
							
							//Try to register the client again
							registerClient();
							
						} else {
							console.log("Auto re-registering the client is disabled. Please contact support. Clearing you browser cache and localStorage container will fix this problem but will require you to reauthenticate.");
						}
	
					//We don't recover from this error
					}
					else {
						ttl = 0;
					}
	
				//Make sure request doesn't re-run
				}
				else {
					ttl = 0;
				}
			}
			
			//Should we re-run the request?
			if (ttl > 0) {
				request(method, data, successCallback, failureCallback, token);

			} else {

				//Call the user defined error handler
				if (typeof failureCallback === "function") {
					failureCallback( response, xmlhttp.status, method, data, (token === null ? null : token.id) );
				} else {
					throw [response, xmlhttp.status, method, data, (token === null ? null : token.id) ];
				}
			}
		};

		xmlhttp.send(
			JSON.stringify({
				"method": method,
				"meta": meta,
				"data": data
			})
		);

		return true;
	}
	
	function addListener(event, callback){
		
		if (["defaultToken"].indexOf(event) === -1){
			throw "addListener: Invalid event type";
		}
		
		if (typeof callback !== "function"){
			throw "addListener: Callback isn't a function";
		}
		
		if (listeners[event].indexOf(callback)!==-1){
			return true;
		}
		
		listeners[event].push(callback);
		
		return true;
		
	}
	
	function removeListener(event, callback){
	
		if (["defaultToken"].indexOf(event) === -1){
			throw "addListener: Invalid event type";
		}
		
		//Does the callback exist?
		if (listeners[event].indexOf(callback) === -1){
			return false;
		}
		
		//Remove the listener
		listeners[event].splice(listeners[event].indexOf(callback), 1);
		
		return true;
	}
	
	function callListener(event, response){
		
		if (typeof listeners[event] !== "object"){
			return false;
		}
		
		for (var prop in listeners[event]) {
			if (listeners[event].hasOwnProperty(prop) && typeof listeners[event][prop] === "function"){
				listeners[event][prop](response);
			}
		}
		
		return true;
	}

	function storeServerTimeOffset(timestamp) {

		var sDate = new Date(timestamp),
			newServerTimeOffset = sDate.getTime() - Date.now();;
		console.log("Stored server clock offset: " + newServerTimeOffset + "ms (previously " + serverTimeOffset + "ms)");
		serverTimeOffset = newServerTimeOffset;

	}

	function getServerDate() {
		return new Date(Date.now() + serverTimeOffset);
	}

	function generateSalt(){
		return (Math.random().toString(36) + '00000000000000000').slice(2, 10 + 2);
	}

};
