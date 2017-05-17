var swdapi = swdapi || function(URI, config){
	
	//Shims
	function defaultFor(arg, val) {
		return typeof arg !== 'undefined' ? arg : val;
	}
	
	config = defaultFor(config, {});
	
	///////////////////////////////
	// Begin constructor
	
	//Test that needed components exist
	if (XMLHttpRequest===undefined){
		throw "SWDAPI: Required component 'XMLHttpRequest' not defined.";
	}
	if (Date.now===undefined){
		throw "SWDAPI: Required component 'Date.now' not defined.";
	}
	if (Number.isInteger===undefined){
		throw "SWDAPI: Required component 'Number.isInteger' not defined.";
	}
	if (console.log===undefined){
		throw "SWDAPI: Required component 'console.log' not defined.";
	}	
	if (forge_sha256===undefined){
		throw "SWDAPI: Required component 'forge_sha256' not defined. Did you forget to include it?";
	}
    
    //Variable for this API instance
    var endpointURI = URI,
        serverTimeOffset = 0,
        defaultToken = null,
        fetchClientData = (typeof config['fetchClientData']==="function" ? config['fetchClientData'] : fetchClientData_Default),
        storeClientData = (typeof config['storeClientData']==="function" ? config['storeClientData'] : storeClientData_Default),
        
        //Defined the public object
        pub = {
        	"login": function(user,pass,callback){
        		callback = defaultFor(callback, null);
        		return getAuthToken(user, pass, function(token){;
        			//If login successfull, store the token for future use
        			if (typeof token == "object"){
        				defaultToken = token;
        			}
        			//Execute callback whether login successfull or not
		        	if (typeof callback === "function"){
		        		callback(token);
	        		}        			
        		});
        	},
        	"logout": function(callback){
        		callback = defaultFor(callback, null);
        		//Are we currently logged in?
        		if (defaultToken!==null){
        			console.log("Requesting logout.")
        			request("swdapi/invalidateToken",{"id":defaultToken},handler,handler);
        			defaultToken = null;
        			return true;
        			
        		//Not logged in, just run callback with true
        		} else {
        			console.log("logout called when not logged in.")
        		    if (typeof callback === "function"){
		        		callback(true);
	        		}
	        		return true;
        		}
        		
        		//Handle logout request
        		var handler = function(response){
        			if (response===true){
        				console.log("Logout completed");
        			} else {
        				console.log("Logout failed");
        			}
    				if (typeof callback === "function"){
		        		callback(response);
        			}   
        		}
        		
        	},
        	"getAuthToken":getAuthToken(),
	        "request": request,
	        "serverDate": getServerDate,
	        "registerClient": registerClient,
	        "setFetchClientDataHandler": function(handler){
	        	if (typeof handler === "function"){
	        		fetchClientData = handler;
	        		return true;
	        	}
	        	throw "Could not store fetchClientData handler. Not a callable function.";
	        },
	        "setStoreClientDataHandler": function(handler){
	        	if (typeof handler === "function"){
	        		storeClientData = handler;
	        		return true;
	        	}
	        	throw "Could not store storeClientData handler. Not a callable function.";
	        }
    	};
    
	//Check if any client info was passed to the constructor
	if (config.setClientName!==undefined && typeof config.setClientName === "string"){
		
		//Is it different from what we have stored?
		var tmpData = fetchClientData();
		if (typeof tmpData !== "object" || tmpData.name === undefined || tmpData.name!==config.setClientName.substring(0,140)){
			//Register the new name
			registerClient(config.setClientName);
		}
	}

	//If the serverTimestamp was specified use it
	if (config['serverTimestamp']!==undefined){
		
		//Check that the supplied timestamp is correct format
		if (Number.isInteger(config['serverTimestamp'])===true){
			
			storeServerTimeOffset(config['serverTimestamp']*1000);
			
		} else {
			console.log("Supplied serverTimestamp was an invalid format. Must be positive integer. The server time will be noted on the next request instead.");
		}
	}
	

    //Return the public object
    return pub;
    
    // End constructor
    //////////////////////////////////////////
    
    
    
    //////////////////////////////////////////
    //Define private methods
        
    function generateMeta(method, data, token){
		
		var meta = {},
			sT = getServerDate().getTime(),
			client = fetchClientData();
			
		//Client
		if (client.id!==undefined && client.secret!==undefined){
			meta.client = {"id":client.id};
		}
		
		//User auth Token
		if (token!==null && token.id!==undefined && token.uid!==undefined){
			meta.token = {
				"id":token.id,
				"uid":token.uid
			};
		}
		
		//Nonce
		meta.nonce = (Math.random().toString(36)+'00000000000000000').slice(2, 10+2);
		
		//Set expiry to be +/- 1 minute
		meta.valid = { 
			"from": Math.floor(sT / 1000)-(60),
			"to": Math.floor(sT / 1000)+(60)
		};
		
		//Sign request
		meta.signature = signRequest(method, meta, data, token);
		
		return meta;
	}
	
	function signRequest(method, meta, data, token){
		
		var text, keyPlain, keyEnc, client = fetchClientData();
		
		text = JSON.stringify([method, meta, data]);
		keyPlain = "swdapi";
		
		//Include client secret in hmac key if registered
		if (meta.client !==undefined && meta.client.id!==undefined && client.secret!==undefined){
			keyPlain += client.id+client.secret;
		}
		
		//Include user key and pin in hmac key
		if (meta.token!==undefined && token.id!==undefined && token.secret!==undefined){
			keyPlain += token.id+token.secret
		}
		
		//Join the dots and hash
		keyEnc = forge_sha256(text+keyPlain);
		
		return keyEnc;
	}
	
	
	//Attempts to "log-in" and fetch user session token
	//
	// Expiry is when the token definitely is invalidated (as a unix timestamp)
	// Timeout is the amount of seconds of inactivity when the server will invalidate the token (in seconds from now)
	//
	// Returns an array []
	//  uid 	= int - the id of the user account this token is for
	//	id		= int - the id of the session token
	//  secret	= string 64 - the secret key
	//  expiry	= int - unixtime that this token expires
	//  timeout = int - seconds of inactivity after which the token will expire
	// ]
	
	function getAuthToken(user, pass, callback, requestExpiry, requestTimeout){
		
		callback		= defaultFor(callback, null);
		requestExpiry	= defaultFor(requestExpiry, null);
		requestTimeout	= defaultFor(requestTimeout, null);
		
		//Todo
		if (typeof callback === "function"){
			callback(true);
		}
		
		return true;
	}
	
	function checkToken(token){
		
		//todo: check type and validity
		return true;
	}
	
	//Set or change the name of the client/terminal/device/whatever
	//and get a unique id-sceret pair
	//which should be stored semi-permenantly
	function registerClient(name, callback){
		
		name		= defaultFor(name, null);
		callback	= defaultFor(callback, null);
		
		var currentData = fetchClientData(),
			sendData = {
				"salt": (Math.random().toString(36)+'00000000000000000').slice(2, 10+2)
			},
			callbackHandler;
		
		//Set id if known
		if (typeof currentData === "object" && currentData.id!==undefined){
			sendData.id = currentData.id;
		}
		
		//Hash/sign the id and secret (if known)
		if (typeof currentData === "object" && currentData.id!==undefined && currentData.secret!==undefined ){
			sendData.signature = forge_sha256("swdapi"+currentData.id+currentData.secret);
		}
		
		//check whether to use new name or old (if it exists)
		if (typeof name ==="string"){
			sendData.name = name.substring(0,140);
		} else if (name!==null){
			throw "Cannot register client. Argument 1 must be either a string or null.";
		} else if (typeof currentData === "object" && currentData.name!==undefined){
			sendData.name = currentData.name;
		} else {
			throw "Cannot register a client without a name. No name is stored and no name was passed to registerClient()";
		}
		
		//////////////////
		//Define a callback handler which will accept data from the server and store it locally
		callbackHandler = function(responseData){
			
			if (typeof responseData !== "object" || responseData.id===undefined || responseData.name===undefined){
				throw "Could not confirm registration of the client. An unexpected error occured.";
			}
			
			var newClientData = {
					"name": responseData.name,
					"id": responseData.id,
				},
				ourSig;
			
			//Did we attempt the request with a client signature?
			
			//Yes, so the server should have sent back a hash for us to compare and confirm it's identity
			if (currentData.id!==undefined && currentData.secret!==undefined){
				//Don't forget to store the current secret again
				newClientData.secret = currentData.secret;
				ourSig = forge_sha256("swdapi"+sendData.salt+sendData.id+currentData.secret);
				if (responseData.signature === undefined || ourSig!==responseData.signature){
					throw	"Failed to confirm the client id. " + 
							"The signature returned by the server doesn't match ours. " +
							"This should be impossible without a man in the middle.";
				}
				
			
			//No, so we should be getting a new secret back	
			} else {
				if (responseData.secret!==undefined){
					newClientData.secret = responseData.secret;
				}
			}
			
			
			//Store the new state
			storeClientData(newClientData);
			console.log("New client registered successfully: " + newClientData.name);
			
			//Call the callback
			if (typeof callback === "function"){
				callback();
			}
		};
		///////////////////////
		
		//Make a request to register/confirm the client
		//Send what data we have even if incomplete
		//The server call will either return some data for us to 
		//store or if we have an expired secret it will return 403
		//(in which case we discard the old id-secret pair and request a new one)
		
		request("swdapi/registerClient", sendData, null,
		
			//Success handler
			callbackHandler,
	
			//Failure handler
			function(responseData){
				
				//Check if this equest failed because our meta.signature was invalid (or id not found)
				if (typeof responseData !== "object" || responseData['SWDAPI-Error']===undefined || !(responseData['SWDAPI-Error'].code===400014 || responseData['SWDAPI-Error'].code===403002)){
					throw "Could not register the client. An unexpected error occured.";
				}
				
				//So, we only reach this point if the client id-secret pair was invalid (expired or corrupted)
				console.log("The client id-secret pair stored on this device has either expired or is corrupt. Requesting a new one.");
				
				//Remove the invalid client id-secret pair identity
				delete currentData.id;
				delete currentData.secret;
				storeClientData({
					"name":sendData.name,
				});
				delete sendData.id;
				delete sendData.signature;
				
				//Run a new request just specifying a client name and a salt value
				//which will return a new id-secret pair for us to use
				request("swdapi/registerClient", sendData, null, callbackHandler,
				
					//If it still fails, just throw an error and give up
					function(){
						throw "Could not register the client. An unexpected error occured.";
					}
					
				);
			}
		);
		

	}
	
	function fetchClientData_Default(){
		if (!storageAvailable("localStorage")){
			throw "Unable to access localStorage API. You must define your own fetchClientData and storeClientData handlers.";
		}
		var client = {};
		if (window.localStorage.getItem("client-id")!==null){
			client.id = window.localStorage.getItem("client-id");
		}
		if (window.localStorage.getItem("client-secret")!==null){
			client.secret = window.localStorage.getItem("client-secret");
		}
		if (window.localStorage.getItem("client-name")!==null){
			client.name = window.localStorage.getItem("client-name");
		}

		return client;
	}
	
	function storeClientData_Default(data){
		if (!storageAvailable("localStorage")){
			throw "Unable to access localStorage API. You must define your own fetchClientData and storeClientData handlers.";
		}
		if (data.id!==undefined){
			window.localStorage.setItem("client-id", data.id);
		} else {
			window.localStorage.removeItem("client-id");
		}
		if (data.secret!==undefined){
			window.localStorage.setItem("client-secret", data.secret);
		} else {
			window.localStorage.removeItem("client-secret");
		}
		if (data.name!==undefined){
			window.localStorage.setItem("client-name", data.name);
		} else {
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
		catch(e) {
			return false;
		}
	}
	
	function request(method, data, successCallback, failureCallback, token){
		
		token			= defaultFor(token, defaultToken);
		successCallback	= defaultFor(successCallback, null);
		failureCallback	= defaultFor(failureCallback, null);
	
    	var xmlhttp = new XMLHttpRequest(),
    		meta = null,
    		ttl = 5;
    		
		//Validate "token" array
		if (!checkToken(token)){
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
			
			//Decode json response
			var response = (xmlhttp.getResponseHeader('content-type')==="application/json" ? JSON.parse(xmlhttp.responseText) : xmlhttp.responseText);
			
			//OK - route response to callback
			if (xmlhttp.status === 200) {
			    if (typeof successCallback === "function"){
			    	successCallback(response, method, data, xmlhttp);
			    }
			    return true;
			  
			}
			
			//Handle non-200 response codes
		
			//Decrease TTL
			ttl-=1;
			
			//Is this a SWDAPI error
			if (typeof response !== "string" && response['SWDAPI-Error'] !==undefined && response['SWDAPI-Error']['code'] !==undefined ){
				
				var code = response['SWDAPI-Error']['code'];
				
				//Is it one we want to recover from?
				
				//valid.from & valid.to errors
				if (code>=400006 && code<=400009){
					
					console.log("Request failed due to expiry data. Reseting system time offset and trying again.");
					
					//Reset serverTimeOffset with date supplied by this request
					storeServerTimeOffset(xmlhttp.getResponseHeader("date"));
					
				//Invalid client id or failed signature
				} else if (code==400014 || code==403002){
					//Warn the developer (as changing client id automatically would de-authorize all connected accounts)
					console.log(
						"SWDAPI: An api request failed because the server reported that the request's signature was invalid."+
						"This could be because the client id-secret pair you are using is invalid or has expired, the authentication token you are using has expired or is invalid or some other secret information embeded in the signature has changed on the server."
					);
					//Don't retry
					ttl = 0;
				
				//We don't recover from this error
				} else {
					ttl = 0;
				}
				
			//Make sure request doesn't re-run
			} else {
				ttl = 0;
			}
			
			//Should we re-run the request?
			if (ttl>0){
				request(method, data, successCallback, failureCallback, token);
				return true;
			}
			
			//Call the user defined error handler
		    if (typeof failureCallback === "function"){
			    failureCallback(response, method, data, token, xmlhttp);
		    }

    	};
    	
    	xmlhttp.send(
    		JSON.stringify({
    		    "method": method,
    			"meta" : meta,
    			"data" : data
    		})
    	);
    	
    	return true;
    }
    
    function storeServerTimeOffset(timestamp){
        
	    var sDate = new Date(timestamp),
	    	newServerTimeOffset = sDate.getTime() - Date.now();; 
		console.log("Stored server clock offset: "+newServerTimeOffset+"ms (previously "+serverTimeOffset+"ms)");
		serverTimeOffset = newServerTimeOffset;	

    }
    
    function getServerDate(){
        return new Date(Date.now()+serverTimeOffset);
    }
	

};