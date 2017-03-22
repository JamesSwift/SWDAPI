<script type="text/javascript" src="submodules/forge-sha256/build/forge-sha256.min.js"></script>
<script type="text/javascript" src="SWDAPI.js"></script>
<script type="text/javascript">
    
    //Create api instance
    var api = swdapi(
        
        //Set url of endpoint
        "example-http.php",
    
        //Send optional config
        {
        
            //Optionally tell the api what time the server thinks it is.
            //If not specified, the api will attempt to use the clients local time to
            //set the expiry data. If the server reports that our expiry is too far in 
            //the future or the past we will use the time the server suplied and automatically
            //retry the request
            //"serverTimestamp": <?php print time(); ?>,
            
            //Give the client a name that the user will recognize (if possible)
            //such as "Front office", "Andrea's laptop"
            //If this information is unavailable, give it a name that helps idenitfy it in 
            //some way such as:
            "setClientName":"SWDAPI Test Client - " + navigator.userAgent
        
        }
    );
    
    //Send a basic request
    api.request(
        
        //Method name
        "ping",
        
        //Data to send
        null,
    
        //Optional callback when response is received
        function(data){
            
            //Dump response into div below
            document.getElementById("output").innerHTML = data;
        },
        
        //Optional callback when none-200 response is received
        console.log
    );    
    
</script>
<div id="output"></div>