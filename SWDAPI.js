var swdapi = swdapi || (function(){
	
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
		throw "SWDAPI: Required component 'forge_sha256' not defined. Did you include it?"
	}
    
    var endpointURI,
        serverTimeOffset = 0;
    
    function generateMeta(method, data){
		
		var meta = {},
			sT = getServerDate().getTime();
		
		//Nonce
		meta.nonce = (Math.random().toString(36)+'00000000000000000').slice(2, 10+2);
		
		//Set expiry to be +/- 1 minute
		meta.valid = { 
			"from": Math.floor(sT / 1000)-(60),
			"to": Math.floor(sT / 1000)+(60)
		};
		
		//Sign with session secret & user pin code
		meta.signature = signRequest(method, meta, data);
		
		return meta;
	}
	
	function signRequest(method, meta, data){
		
		var text, keyPlain, keyEnc;
		
		text = JSON.stringify([method, meta, data]);
		keyPlain = "swdapi";
		
		//Include user key and pin in hmac key
		if (meta.user!==undefined){
			//keyPlain += users[activeUser].sessionKey + users[activeUser].pin;
		}
		
		//Join the dots and hash
		keyEnc = forge_sha256(text+keyPlain);
		
		return keyEnc;
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
	
	return function(URI, onloadCallback=null, srvTmOf=null){
	    
	    //Store the endpoint
	    endpointURI = URI;

		//We need to find the time offset to the server so that we can
		//set request expiry to a nice short time
		
		//If the server offset was specified use it
		if (Number.isInteger(srvTmOf)===true){
			serverTimeOffset = srvTmOf;
			
			if (typeof onloadCallback === "function"){
			    onloadCallback();
			}
		
		//If not, send a HEAD request to API to find server time
		} else {
	        findServerTimeOffset(onloadCallback);
		} 
			
			
        //Return the api object
        return { 
            "request": request,
            "serverDate": getServerDate,
            "authenticate": null
        }
        
	};

}());