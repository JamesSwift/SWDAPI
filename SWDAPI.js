var swdapi = swdapi || function(URI, onloadCallback=null, config={}){
	
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
    	clientVerified = false,
        serverTimeOffset = null,
        
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
	
	//We need to find the time offset to the server so that we can
	//set request expiry to a nice short time
	
	//If the serverTimestamp was specified use it
	if (config['serverTimestamp']!==undefined){
		
		//Check that the supplied timestamp is correct format
		if (Number.isInteger(config['serverTimestamp'])===true){
			
			//Compute difference between server and local time (roughly)
			var st = new Date(config.serverTimestamp*1000);
			serverTimeOffset = st.getTime() - Date.now();
			console.log("Server time offset from us: "+serverTimeOffset+"ms");
			
			//Fire up the Callback
			if (typeof onloadCallback === "function"){
			    setTimeout(onloadCallback, 10);
			}
		} else {
			console.log("Supplied serverTimestamp was an invalid format. Must be positive integer. Querying server for time...");
		}
	}
	
	//If not, send a request to API to find server time
	if (serverTimeOffset===null) {
        findServerTimeOffset(onloadCallback);
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
		if (clientVerified===true && client.id!==undefined && client.secret!==undefined){
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
	
	function request(method, body, successCallback, failureCallback = null){
	
    	var xmlhttp = new XMLHttpRequest(),
    		meta = null;
    		
    	//Generate meta for this request (and sign it)
    	meta = generateMeta(method, body);
    	
    	xmlhttp.open("POST", endpointURI);
    	xmlhttp.setRequestHeader("Content-Type", "application/json;charset=UTF-8");
    	xmlhttp.onreadystatechange = function() {
			if (xmlhttp.readyState === xmlhttp.DONE) {
				if (xmlhttp.status === 200) {
				    if (typeof successCallback === "function"){
				        if (xmlhttp.getResponseHeader('content-type')==="application/json"){
				            successCallback(JSON.parse(xmlhttp.responseText));
				        } else {
					        successCallback(xmlhttp.responseText);
				        }
				    }
				}
				else {
				    if (typeof failureCallback === "function"){
					    failureCallback(xmlhttp);
				    }
				}
			}
    	};
    	
    	xmlhttp.send(
    		JSON.stringify({
    		    "method": method,
    			"meta" : meta,
    			"data" : body
    		})
    	);
    	
    	return true;
    }
    
    function findServerTimeOffset(onloadCallback){
        
        var serverDate, xmlhttp = new XMLHttpRequest();
            
        xmlhttp.open('HEAD', endpointURI);
        xmlhttp.onreadystatechange = function() {
			if (xmlhttp.readyState === xmlhttp.DONE) {
			   
			    serverDate = new Date(xmlhttp.getResponseHeader("Date")); 
				serverTimeOffset = serverDate.getTime() - Date.now();
				console.log("Server time offset from us: "+serverTimeOffset+"ms");
				
				if (typeof onloadCallback === "function"){
				    onloadCallback();
				}
			}
    	};
    	xmlhttp.send();
    	
    };
    
    function getServerDate(){
        return new Date(Date.now()+serverTimeOffset);
    }
	

};