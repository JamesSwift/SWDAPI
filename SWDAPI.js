var swdapi = swdapi || function(URI, config={}){
	
	///////////////////////////////
	// Begin constructor
	
	//Test that needed components exist
	if (XMLHttpRequest===undefined){
		throw "SWDAPI: Required component 'XMLHttpRequest' not defined."
	}
	if (Date.now===undefined){
		throw "SWDAPI: Required component 'Date.now' not defined."
	}
	if (Number.isInteger===undefined){
		throw "SWDAPI: Required component 'Number.isInteger' not defined."
	}
	if (forge_sha256===undefined){
		throw "SWDAPI: Required component 'forge_sha256' not defined. Did you forget to include it?"
	}
    
    //Variable for this API instance
    var endpointURI = URI,
        serverTimeOffset = 0,
        
        //Defined the public object
        pub = { 
	        "request": request,
	        "serverDate": getServerDate,
	        "getClientData": (config['getClientData']!==undefined ? config['getClientData'] : getClientData_Default),
	        "setClientData": (config['setClientData']!==undefined ? config['setClientData'] : setClientData_Default),
    	};
    
	//Check if any client info was passed
	if (config.client!==undefined){
		
		//Save it
		pub.setClientData(config.client);
		
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
        
    function generateMeta(method, data){
		
		var meta = {},
			sT = getServerDate().getTime(),
			client = pub.getClientData();
			
		//Client
		if (client.id!==undefined && client.secret!==undefined){
			meta.client = {"id":client.id};
		}
		
		//Nonce
		meta.nonce = (Math.random().toString(36)+'00000000000000000').slice(2, 10+2);
		
		//Set expiry to be +/- 1 minute
		meta.valid = { 
			"from": Math.floor(sT / 1000)-(60),
			"to": Math.floor(sT / 1000)+(60)
		};
		
		//Sign request
		meta.signature = signRequest(method, meta, data);
		
		return meta;
	}
	
	function signRequest(method, meta, data){
		
		var text, keyPlain, keyEnc, client = pub.getClientData();
		
		text = JSON.stringify([method, meta, data]);
		keyPlain = "swdapi";
		
		//Include client secret in hmac key if registered
		if (meta.client !==undefined && meta.client.id!==undefined && client.secret!==undefined){
			keyPlain += client.secret;
		}
		
		//Include user key and pin in hmac key
		if (meta.user!==undefined){
			//keyPlain += users[activeUser].sessionKey + users[activeUser].pin;
		}
		
		//Join the dots and hash
		keyEnc = forge_sha256(text+keyPlain);
		
		return keyEnc;
	}
	
	function registerClient(callback){
		
		//Make a request to register/re-register the client
		//Send what data we have, if it is incomplete we will be given a new client id and secret
		request("swdapi/registerClient", pub.getClientData(),
			function(){
				//success
			},
			function(){
				//fail
			}
		);
	}
	
	function getClientData_Default(){
		if (!storageAvailable("localStorage")){
			throw "Unable to access localStorage API. You must define your own getClientData and setClientData handlers.";
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
	
	function setClientData_Default(data){
		if (!storageAvailable("localStorage")){
			throw "Unable to access localStorage API. You must define your own getClientData and setClientData handlers.";
		}
		if (data.id!==undefined){
			window.localStorage.setItem("client-id", data.id);
		}
		if (data.secret!==undefined){
			window.localStorage.setItem("client-secret", data.secret);
		}
		if (data.name!==undefined){
			window.localStorage.setItem("client-name", data.name);
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
	
	function request(method, data, successCallback, failureCallback = null){
	
    	var xmlhttp = new XMLHttpRequest(),
    		meta = null,
    		ttl = 5;
    		
    	//Generate meta for this request (and sign it)
    	meta = generateMeta(method, data);
    	
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
			    	successCallback(response, method, data);
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
					
					
				//We don't recover frrm this error
				} else {
					ttl = 0;
				}
				
			//Make sure request doesn't re-run
			} else {
				ttl = 0;
			}
			
			//Should we re-run the request?
			if (ttl>0){
				pub.request(method, data, successCallback, failureCallback);
				return true;
			}
			
			//Call the user defined eror handler
		    if (typeof failureCallback === "function"){
			    failureCallback(xmlhttp, method, data);
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