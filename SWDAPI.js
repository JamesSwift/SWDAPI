

var swdapi = swdapi || (function(){
    
    var endpointURI,
        serverTimeOffset = 0;
    
    function generateMeta(method, body){
		
		var meta = {};
		
		//Nonce
		meta.nonce = (Math.random().toString(36)+'00000000000000000').slice(2, 10+2);
		
		//Set expiry to 2 minutes from now
		meta.expires = Math.floor(getServerDate().getTime() / 1000)+(60*2);
		
		//Response type
		meta.response = "json";
		
		//Sign with session secret & user pin code
		meta.signature = signRequest(method, body, meta);
	}
	
	function signRequest(method, body, meta){
		
		var text, keyPlain, keyEnc;
		
		text = JSON.stringify({method, body, meta});
		keyPlain = "swdapi";
		
		//Include user key and pin in hmac key
		if (meta.user){
			//keyPlain += users[activeUser].sessionKey + users[activeUser].pin;
		}
		
		//Join the dots and hash
		//keyEnc = CryptoJS.SHA256(text+keyPlain).toString();
		
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
    			"body" : body
    		})
    	);
    	
    	return true
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
	
	return function(URI, onloadCallback=null){
	    
	    //Store the endpoint
	    endpointURI = URI;

        //Send a HEAD request to API to find server time and store in serverTimeOffset
        findServerTimeOffset(onloadCallback);
        
        return { 
            "request": request,
            "serverDate": getServerDate,
            "authenticate": endpointURI
        }
        
	};

}());