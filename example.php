<script type="text/javascript" src="submodules/forge-sha256/build/forge-sha256.min.js"></script>
<script type="text/javascript" src="SWDAPI.js"></script>
<script type="text/javascript">
    
    //Create api instance
    var api = swdapi(
        
        //Set url of endpoint
        "example-http.php",
    
        //Callback when api is ready
        function(){
    
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
            
        }
        
        //Optionally tell the api what time the server thinks it is.
        //If not specified, the api makes a HEAD request to the server
        //to find the time
        //,{ "serverTimestamp": <?php print time(); ?>}
    );
    
</script>
<div id="output"></div>