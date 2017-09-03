<h1>
SWDAPI - A php API framework
<a rel="license" href="http://creativecommons.org/licenses/by-sa/3.0/deed.en_US" style="float:right;"><img alt="Creative Commons License" style="border-width:0" src="http://i.creativecommons.org/l/by-sa/3.0/88x31.png" /></a>
</h1>

I created my own API framework rather than using someone else's because:

- I want to be able to call the API over http *AND* from within a PHP website with as little overhead as possible
- I want an API that better reflects the function($variables) style that programmers are used to, instead of /function/$variable/function/$variable that REST uses

## Get The Code
To get a copy of the code, at your terminal type:

    git clone git://github.com/JamesSwift/SWDAPI.git
    git submodule update --init -r
    
You then need to setup the database. Assuming the default mysql root account run:

    mysql -u root < db.sql
    
## Configuration

See examples included and the [wiki](https://github.com/JamesSwift/SWDAPI/wiki/Server-Configuration) for help to configure the api.

You will notice that SWDAPI doesn't define regex patterns which match to a view. I've taken a different approach to most APIs which use the 
"/api/users/$uid/$option" approach. Instead requests are to a fixed endpoint (e.g. mysite.com/api), and you simply specify which method you want to invoke
and pass variables alongside. This is much the same way as you would normally do with oop programming. This allows SWDAPI to avoid runing multiple regex
passes and keep the contorller execution time down to a minimum. It also just makes more sense (at least to me).

The SWDAPI controller makes use of OOP classes when advantageous, but doesn't demand them everywhere unlike most other projects. Classes are getting
more efficient, but they still come with resource costs. Why define a class and instantiate it when you could simply pass an array? Hence, to define 
API endpoints, you simply do:

    "methods": {
    
        ... some method definitions ...
        
        "getUserPID": {
            "src": "users-functions/getUserPid.php",
            "call": "getUserPid"
        }
        
        ... more method definitions ...
    }
    
When the api receives a request for that endpoint, it literally checks if `$methods['getUserPid']` exists, then does a require_once on 
`$methods['getUserPid']['src']` and then executes the function named `$methods['getUserPid']['call']` (passing in the request data). Simple 
and quick. It then waits for a `Response()` class to be returned which it processes and outputs.

## License

<span xmlns:dct="http://purl.org/dc/terms/" property="dct:title">JamesSwift\SWDAPI</span> by 
<a xmlns:cc="http://creativecommons.org/ns#" href="https://github.com/JamesSwift/SWDAPI" property="cc:attributionName" rel="cc:attributionURL">James Swift</a>
 is licensed under a <a rel="license" href="http://creativecommons.org/licenses/by-sa/3.0/deed.en_US">Creative Commons Attribution-ShareAlike 3.0 Unported License</a>.
