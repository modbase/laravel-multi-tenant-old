<?php
/**
* The base XML-API class
*
* The XML-API class allows for easy execution of cPanel XML-API calls.  The goal of this project is to create 
* an open source library that can be used for multiple types of applications.  This class relies on PHP5 compiled
* with both curl and simplexml support.
*
* Making Calls with this class are done in the following steps:
*
* 1.) Instaniating the class:
* $xmlapi = new xmlapi($host);
*
* 2.) Setting access credentials within the class via either set_password or set_hash:
* $xmlapi->set_hash("username", $accessHash);
* $xmlapi->set_password("username", "password");
* 
* 3.) Execute a function
* $xmlapi->listaccts();
*
* @category Cpanel
* @package xmlapi
* @copyright 2011 cPanel, Inc.
* @license http://sdk.cpanel.net/license/bsd.html
* @version Release: 1.0.11
* @link http://twiki.cpanel.net/twiki/bin/view/AllDocumentation/AutomationIntegration/XmlApi
* @since Class available since release 0.1
**/

class Cpanel {
	// should debugging statements be printed?
	private $debug			= false;
	
	// The host to connect to
	private $host				=	'127.0.0.1';

	// the port to connect to
	private $port				=	'2087';

	// should be the literal strings http or https
	private $protocol		=	'https';

	// output that should be given by the xml-api
	private $output		=	'simplexml';

	// literal strings hash or password
	private $auth_type 	= null;

	//  the actual password or hash
	private $auth 			= null;
	
	// username to authenticate as
	private $user				= null;
	
	// The HTTP Client to use
	
	private $http_client		= 'curl';
	
	/**
	* Instantiate the XML-API Object
	* All parameters to this function are optional and can be set via the accessor functions or constants
	* This defaults to password auth, however set_hash can be used to use hash authentication
	*
	* @param string $host The host to perform queries on
	* @param string $user The username to authenticate as
	* @param string $password The password to authenticate with
	* @return Xml_Api object
	*/
	public function __construct($host = null, $user = null, $password = null ) {
		
		// Check if debugging must be enabled
		if ( (defined('XMLAPI_DEBUG')) && (XMLAPI_DEBUG == '1') ) {
		 	$this->debug = true;
		}

		// Check if raw xml output must be enabled
		if ( (defined('XMLAPI_RAW_XML')) && (XMLAPI_RAW_XML == '1') ) {
		 	$this->raw_xml = true;
		}
		
		/**
		* Authentication
		* This can either be passed at this point or by using the set_hash or set_password functions
		**/
		
		if ( ( defined('XMLAPI_USER') ) && ( strlen(XMLAPI_USER) > 0 ) ) {
			$this->user = XMLAPI_USER;
	
			// set the authtype to pass and place the password in $this->pass
			if ( ( defined('XMLAPI_PASS') ) && ( strlen(XMLAPI_PASS) > 0 ) ) {
				$this->auth_type = 'pass';
				$this->auth = XMLAPI_PASS;
			}

			// set the authtype to hash and place the hash in $this->auth
			if ( ( defined('XMLAPI_HASH') ) && ( strlen(XMLAPI_HASH) > 0 ) ) {
				$this->auth_type = 'hash';
				$this->auth = preg_replace("/(\n|\r|\s)/", '', XMLAPI_HASH);
			}
			
			// Throw warning if XMLAPI_HASH and XMLAPI_PASS are defined
			if ( ( ( defined('XMLAPI_HASH') ) && ( strlen(XMLAPI_HASH) > 0 ) ) 
				&& ( ( defined('XMLAPI_PASS') ) && ( strlen(XMLAPI_PASS) > 0 ) ) ) {
				error_log('warning: both XMLAPI_HASH and XMLAPI_PASS are defined, defaulting to XMLAPI_HASH');
			}
			
			
			// Throw a warning if XMLAPI_HASH and XMLAPI_PASS are undefined and XMLAPI_USER is defined
			if ( !(defined('XMLAPI_HASH') ) || !defined('XMLAPI_PASS') ) {
				error_log('warning: XMLAPI_USER set but neither XMLAPI_HASH or XMLAPI_PASS have not been defined');
			}
			
		}
		
		if ( ( $user != null ) && ( strlen( $user ) < 9 ) ) {
			$this->user = $user;
		}
		
		if ($password != null ) {
			$this->set_password($password);
		}
		
		/**
		* Connection
		* 
		* $host/XMLAPI_HOST should always be equal to either the IP of the server or it's hostname
		*/
		
		// Set the host, error if not defined
		if ( $host == null ) {
			if ( (defined('XMLAPI_HOST')) && (strlen(XMLAPI_HOST) > 0) ) {
				$this->host = XMLAPI_HOST;
			} else {
				throw new Exception("No host defined");
			}
		} else {
			$this->host = $host;
		}
		

		// disabling SSL is probably a bad idea.. just saying.		
		if ( defined('XMLAPI_USE_SSL' ) && (XMLAPI_USE_SSL == '0' ) ) {
			$this->protocol = "http";
		}
		
		// Detemine what the default http client should be.
		if ( function_exists('curl_setopt') ) {
			$this->http_client = "curl";
		} elseif ( ini_get('allow_url_fopen') ) {
			$this->http_client = "fopen";
		} else {
			throw new Exception('allow_url_fopen and curl are neither available in this PHP configuration');
		}
		
	}
	
	/**
	* Accessor Functions
	**/
	/**
	* Return whether the debug option is set within the object
	*
	* @return boolean
	* @see set_debug()
	*/
	public function get_debug() {
		return $this->debug;
	}
	
	/**
	* Turn on debug mode
	*
	* Enabling this option will cause this script to print debug information such as
	* the queries made, the response XML/JSON and other such pertinent information.
	* Calling this function without any parameters will enable debug mode.
	*
	* @param bool $debug turn on or off debug mode
	* @see get_debug()
	*/
	public function set_debug( $debug = 1 ) {
		$this->debug = $debug;
	}
	
	/**
	* Get the host being connected to
	*
	* This function will return the host being connected to
	* @return string host
	* @see set_host()
	*/
	public function get_host() {
		return $this->host;
	}
	
	/**
	* Set the host to query
	*
	* Setting this will set the host to be queried
	* @param string $host The host to query
	* @see get_host()
	*/
	public function set_host( $host ) {
		$this->host = $host;
	}
	
	/**
	* Get the port to connect to
	*
	* This will return which port the class is connecting to
	* @return int $port
	* @see set_port()
	*/
	public function get_port() {
		return $this->port;
	}
	
	/**
	* Set the port to connect to
	*
	* This will allow a user to define which port needs to be connected to.
	* The default port set within the class is 2087 (WHM-SSL) however other ports are optional
	* this function will automatically set the protocol to http if the port is equal to:
	*    - 2082
	*    - 2086
	*    - 2095
	*    - 80
	* @param int $port the port to connect to
	* @see set_protocol()
	* @see get_port()
	*/
	public function set_port( $port ) {
		if ( !is_int( $port ) ) {
			$port = intval($port);
		}
		
		if ( $port < 1 || $port > 65535 ) {
			throw new Exception('non integer or negative integer passed to set_port');
		}
		
		// Account for ports that are non-ssl
		if ( $port == '2086' || $port == '2082' || $port == '80' || $port == '2095' ) {
			$this->set_protocol('http');
		}
		
		$this->port = $port;
	}
	
	/**
	* Return the protocol being used to query
	*
	* This will return the protocol being connected to
	* @return string
	* @see set_protocol()
	*/
	public function get_protocol() {
		return $this->protocol;
	}
	
	/**
	* Set the protocol to use to query
	*
	* This will allow you to set the protocol to query cpsrvd with.  The only to acceptable values
	* to be passed to this function are 'http' or 'https'.  Anything else will cause the class to throw
	* an Exception.
	* @param string $proto the protocol to use to connect to cpsrvd
	* @see get_protocol()
	*/
	public function set_protocol( $proto ) {
		if ( $proto != 'https' && $proto != 'http' ) {
			throw new Exception('https and http are the only protocols that can be passed to set_protocol');
		}
		$this->protocol = $proto;
	}
	
	/** 
	* Return what format calls with be returned in
	*
	* This function will return the currently set output format
	* @see set_output()
	* @return string
	*/
	public function get_output() {
		return $this->output;
	}
	
	/**
	* Set the output format for call functions
	*
	* This class is capable of returning data in numerous formats including:
	*   - json
	*   - xml
	*   - {@link http://php.net/simplexml SimpleXML}
	*   - {@link http://us.php.net/manual/en/language.types.array.php Associative Arrays}
	*
	* These can be set by passing this class any of the following values:
	*   - json - return JSON string
	*   - xml - return XML string
	*   - simplexml - return SimpleXML object
	*   - array - Return an associative array
	*
	* Passing any value other than these to this class will cause an Exception to be thrown.
	* @param string $output the output type to be set
	* @see get_output()
	*/
	public function set_output( $output ) {
		if ( $output != 'json' && $output != 'xml' && $output != 'array' && $output != 'simplexml' ) {
			throw new Exception('json, xml, array and simplexml are the only allowed values for set_output');
		}
		$this->output = $output;
	}
	
	/**
	* Return the auth_type being used
	*
	* This function will return a string containing the auth type in use
	* @return string auth type
	* @see set_auth_type()
	*/
	public function get_auth_type() {
		return $this->auth_type;
	}
	
	/**
	* Set the auth type
	*
	* This class is capable of authenticating with both hash auth and password auth
	* This function will allow you to manually set which auth_type you are using.
	*
	* the only accepted parameters for this function are "hash" and "pass" anything else will cuase
	* an exception to be thrown
	*
	* @see set_password()
	* @see set_hash()
	* @see get_auth_type()
	* @param string auth_type the auth type to be set
	*/
	public function set_auth_type( $auth_type ) {
		if ( $auth_type != 'hash' && $auth_type != 'pass') {
			throw new Exception('the only two allowable auth types arehash and path');
		}
		$this->auth_type = $auth_type;
	}
	
	/**
	* Set the password to be autenticated with
	*
	* This will set the password to be authenticated with, the auth_type will be automatically adjusted
	* when this function is used
	*
	* @param string $pass the password to authenticate with
	* @see set_hash()
	* @see set_auth_type()
	* @see set_user()
	*/
	public function set_password( $pass ) {
		$this->auth_type = 'pass';
		$this->auth = $pass;
	}
	
	/**
	* Set the hash to authenticate with
	*
	* This will set the hash to authenticate with, the auth_type will automatically be set when this function
	* is used.  This function will automatically strip the newlines from the hash.
	* @param string $hash the hash to autenticate with
	* @see set_password()
	* @see set_auth_type()
	* @see set_user()
	*/
	public function set_hash( $hash ) {
		$this->auth_type = 'hash';
		$this->auth = preg_replace("/(\n|\r|\s)/", '', $hash);
	}
	
	/**
	* Return the user being used for authtication
	*
	* This will return the username being authenticated against.
	*
	* @return string
	*/
	public function get_user() {
		return $this->user;
	}
	
	/**
	* Set the user to authenticate against
	*
	* This will set the user being authenticated against.
	* @param string $user username
	* @see set_password()
	* @see set_hash()
	* @see get_user()
	*/
	public function set_user( $user ) {
		$this->user = $user;
	}
	
	/**
	* Set the user and hash to be used for authentication
	*
	* This function will allow one to set the user AND hash to be authenticated with
	* 
	* @param string $user username
	* @param string $hash WHM Access Hash
	* @see set_hash()
	* @see set_user()
	*/
	public function hash_auth( $user, $hash ) {
		$this->set_hash( $hash );
		$this->set_user( $user );
	}
	
	/**
	* Set the user and password to be used for authentication
	*
	* This function will allow one to set the user AND password to be authenticated with
	* @param string $user username
	* @param string $pass password
	* @see set_pass()
	* @see set_user()
	*/
	public function password_auth( $user, $pass ) {
		$this->set_password( $pass );
		$this->set_user( $user );
	}
	
	/**
	* Return XML format
	*
	* this function will cause call functions to return XML format, this is the same as doing:
	*   set_output('xml')
	*
	* @see set_output()
	*/
	public function return_xml() {
		$this->set_output('xml');
	}
	
	/**
	* Return simplexml format
	*
	* this function will cause all call functions to return simplexml format, this is the same as doing:
	*   set_output('simplexml')
	*
	* @see set_output()
	*/
	public function return_object() {
		$this->set_output('simplexml');
	}


	/**
	* Set the HTTP client to use
	*
	* This class is capable of two types of HTTP Clients:
	*   - curl
	*   - fopen
	*
	* When using allow url fopen the class will use get_file_contents to perform the query
	* The only two acceptable parameters for this function are 'curl' and 'fopen'.
	* This will default to fopen, however if allow_url_fopen is disabled inside of php.ini
	* it will switch to curl
	*
 	* @param string client The http client to use
	* @see get_http_client()
	*/
	
	public function set_http_client( $client ) {
		if ( ( $client != 'curl' ) && ( $client != 'fopen' ) ) {
			throw new Exception('only curl and fopen and allowed http clients');
		}
		$this->http_client = $client;
	}
	
	/**
	* Get the HTTP Client in use
	*
	* This will return a string containing the HTTP client currently in use
	*
	* @see set_http_client()
	* @return string
	*/
	public function get_http_client() {
		return $this->http_client;
	}
	
 	/*	
	*	Query Functions
	*	--
	*	This is where the actual calling of the XML-API, building API1 & API2 calls happens
	*/
	
	/**
	* Perform an XML-API Query
	*
	* This function will perform an XML-API Query and return the specified output format of the call being made
	*
	* @param string $function The XML-API call to execute
	* @param array $vars An associative array of the parameters to be passed to the XML-API Calls
	* @return mixed
	*/
	public function xmlapi_query( $function, $vars = array() ) {
		
		// Check to make sure all the data needed to perform the query is in place
		if (!$function) {
			throw new Exception('xmlapi_query() requires a function to be passed to it');
		}

		if ($this->user == null ) {
			throw new Exception('no user has been set');
		}
		
		if ($this->auth ==null) {
			throw new Exception('no authentication information has been set');
		}

		// Build the query:
		
		$query_type = '/xml-api/';
		
		if ( $this->output == 'json' ) {
			$query_type = '/json-api/';
		}
		
		$args = http_build_query($vars, '', '&');
		$url =  $this->protocol . '://' . $this->host . ':' . $this->port . $query_type . $function;

		if ($this->debug) {
			error_log('URL: ' . $url);
			error_log('DATA: ' . $args);
		}

		// Set the $auth string
		
		$authstr;
		if ( $this->auth_type == 'hash' ) {
			$authstr = 'Authorization: WHM ' . $this->user . ':' . $this->auth . "\r\n";
		} elseif ($this->auth_type == 'pass' ) {
			$authstr = 'Authorization: Basic ' . base64_encode($this->user .':'. $this->auth) . "\r\n";
		} else {
			throw new Exception('invalid auth_type set');
		}
		
		if ($this->debug) {
			error_log("Authentication Header: " . $authstr ."\n");
		}

		// Perform the query (or pass the info to the functions that actually do perform the query)
		
		$response;
		if ( $this->http_client == 'curl' ) {
			$response = $this->curl_query($url, $args, $authstr);
		} elseif ( $this->http_client == 'fopen' ) {
			$response = $this->fopen_query($url, $args, $authstr);
		}
		

				
		/*
		*	Post-Query Block
		* Handle response, return proper data types, debug, etc
		*/
		
		// print out the response if debug mode is enabled.
		if ($this->debug) {
			error_log("RESPONSE:\n " . $response);
		}
		
		// The only time a response should contain <html> is in the case of authentication error
		// cPanel 11.25 fixes this issue, but if <html> is in the response, we'll error out.
		
		if (stristr($response, '<html>') == true) {
			if (stristr($response, 'Login Attempt Failed') == true) {
				error_log("Login Attempt Failed");
				return;
			}
			if (stristr($response, 'action="/login/"') == true) {
				error_log("Authentication Error");
				return;
			}
			return;
		}
		
		
		// perform simplexml transformation (array relies on this)
		if ( ($this->output == 'simplexml') || $this->output == 'array') {
			$response = simplexml_load_string($response, null, LIBXML_NOERROR | LIBXML_NOWARNING);
			if (!$response){
			        error_log("Some error message here");
			        return;
			}
			if ( $this->debug ) {
				error_log("SimpleXML var_dump:\n" . print_r($response, true));
			}
		}
		
		// perform array tranformation
		if ($this->output == 'array') {
			$response = $this->unserialize_xml($response);
			if ( $this->debug ) {
				error_log("Associative Array var_dump:\n" . print_r($response, true));
			}
		}
		return $response;
	}
	
	private function curl_query( $url, $postdata, $authstr ) {
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
		// Return contents of transfer on curl_exec
 		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		// Allow self-signed certs
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
		// Set the URL
		curl_setopt($curl, CURLOPT_URL, $url);
		// Increase buffer size to avoid "funny output" exception
		curl_setopt($curl, CURLOPT_BUFFERSIZE, 131072);
	
		// Pass authentication header
		$header[0] =$authstr .
			"Content-Type: application/x-www-form-urlencoded\r\n" .
			"Content-Length: " . strlen($postdata) . "\r\n" . "\r\n" . $postdata;
		
		curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
			
		curl_setopt($curl, CURLOPT_POST, 1);

		$result = curl_exec($curl);
		if ($result == false) {
			throw new Exception("curl_exec threw error \"" . curl_error($curl) . "\" for " . $url . "?" . $postdata );
		}
		curl_close($curl);
		return $result;
	}
	
	private function fopen_query( $url, $postdata, $authstr ) {
		if ( !(ini_get('allow_url_fopen') ) ) {
			throw new Exception('fopen_query called on system without allow_url_fopen enabled in php.ini');
		}
		
		$opts = array(
			'http' => array(
				'allow_self_signed' => true,
				'method' => 'POST',
				'header' => $authstr .
					"Content-Type: application/x-www-form-urlencoded\r\n" .
					"Content-Length: " . strlen($postdata) . "\r\n" .
					"\r\n" . $postdata
			)
		);
		$context = stream_context_create($opts);
		return file_get_contents($url, false, $context);
	}
	
	
	/*
	* Convert simplexml to associative arrays
	*
	* This function will convert simplexml to associative arrays.
	*/
	private function unserialize_xml($input, $callback = null, $recurse = false) {
		// Get input, loading an xml string with simplexml if its the top level of recursion
		$data = ( (!$recurse) && is_string($input) ) ? simplexml_load_string($input) : $input;
		// Convert SimpleXMLElements to array
		if ($data instanceof SimpleXMLElement) {
			$data = (array) $data;
		}
		// Recurse into arrays
		if (is_array($data)) {
			foreach ($data as &$item) {
				$item = $this->unserialize_xml($item, $callback, true);
			}
		}
		// Run callback and return
		return (!is_array($data) && is_callable($callback)) ? call_user_func($callback, $data) : $data;
	}
	
	
	/* TO DO:
	  Implement API1 and API2 query functions!!!!!
	*/
	/**
	* Call an API1 function
	*
	* This function allows you to call API1 from within the XML-API,  This allowes a user to peform actions
	* such as adding ftp accounts, etc
	*
	* @param string $user The username of the account to perform API1 actions on
	* @param string $module The module of the API1 call to use
	* @param string $function The function of the API1 call
	* @param array $args The arguments for the API1 function, this should be a non-associative array
	* @return mixed
	* @link http://docs.cpanel.net/twiki/bin/view/AllDocumentation/AutomationIntegration/CallingAPIFunctions XML API Call documentation
	* @link http://docs.cpanel.net/twiki/bin/view/DeveloperResources/ApiRef/WebHome API1 & API2 Call documentation
	* @link http://docs.cpanel.net/twiki/bin/view/DeveloperResources/ApiBasics/CallingApiOne API1 Documentation
	*/
	public function api1_query($user, $module, $function, $args = array() ) {
		if ( !isset($module) || !isset($function) || !isset($user) ) {
			error_log("api1_query requires that a module and function are passed to it");
			return false;
		}
		
		if (!is_array($args)) {
			error_log('api1_query requires that it is passed an array as the 4th parameter');
			return false;
		}
		
		$cpuser = 'cpanel_xmlapi_user';
		$module_type = 'cpanel_xmlapi_module';
		$func_type = 'cpanel_xmlapi_func';
		$api_type = 'cpanel_xmlapi_apiversion';		

		if ( $this->get_output() == 'json' ) {
		    $cpuser = 'cpanel_jsonapi_user';
			$module_type = 'cpanel_jsonapi_module';
			$func_type = 'cpanel_jsonapi_func';
			$api_type = 'cpanel_jsonapi_apiversion';			
		}
		
		$call = array(
				$cpuser => $user,
				$module_type => $module,
				$func_type => $function,
				$api_type => '1'
			);
		for ($int = 0; $int < count($args);  $int++) {
			$call['arg-' . $int] = $args[$int];
		}
		return $this->xmlapi_query('cpanel', $call);
	}
	
	/**
	* Call an API2 Function
	*
	* This function allows you to call an API2 function, this is the modern API for cPanel and should be used in preference over
	* API1 when possible
	*
	* @param string $user The username of the account to perform API2 actions on
	* @param string $module The module of the API2 call to use
	* @param string $function The function of the API2 call
	* @param array $args An associative array containing the arguments for the API2 call
	* @return mixed
	* @link http://docs.cpanel.net/twiki/bin/view/AllDocumentation/AutomationIntegration/CallingAPIFunctions XML API Call documentation
	* @link http://docs.cpanel.net/twiki/bin/view/DeveloperResources/ApiRef/WebHome API1 & API2 Call documentation
	* @link http://docs.cpanel.net/twiki/bin/view/AllDocumentation/AutomationIntegration/ApiTwo Legacy API2 Documentation
	* @link http://docs.cpanel.net/twiki/bin/view/DeveloperResources/ApiBasics/CallingApiTwo API2 Documentation
	*/ 
	
	public function api2_query($user, $module, $function, $args = array()) {
		if (!isset($user) || !isset($module) || !isset($function) ) {
			error_log("api2_query requires that a username, module and function are passed to it");
			return false;
		}
		if (!is_array($args)) {
			error_log("api2_query requires that an array is passed to it as the 4th parameter");
			return false;
		}
		
		$cpuser = 'cpanel_xmlapi_user';
		$module_type = 'cpanel_xmlapi_module';
		$func_type = 'cpanel_xmlapi_func';
		$api_type = 'cpanel_xmlapi_apiversion';

		if ( $this->get_output() == 'json' ) {
		    $cpuser = 'cpanel_jsonapi_user';
			$module_type = 'cpanel_jsonapi_module';
			$func_type = 'cpanel_jsonapi_func';
			$api_type = 'cpanel_jsonapi_apiversion';
		}
		
		$args[$cpuser] = $user;
		$args[$module_type] = $module;
		$args[$func_type] = $function;
		$args[$api_type] = '2';
		return $this->xmlapi_query('cpanel', $args);
	}
	
	####
	#  XML API Functions
	####

	/**
	* Return a list of available XML-API calls
	*
	* This function will return an array containing all applications available within the XML-API
	*
	* @return mixed
	* @link http://docs.cpanel.net/twiki/bin/view/AllDocumentation/AutomationIntegration/ListAvailableCalls XML API Call documentation
	*/
	public function applist() {
		return $this->xmlapi_query('applist');
	}

	####
	# DNS Functions
	####

	// This API function lets you create a DNS zone.
	/**
	* Add a DNS Zone
	*
	* This XML API function will create a DNS Zone.  This will use the "standard" template when
	* creating the zone.
	*
	* @param string $domain The DNS Domain that you wish to create a zone for
	* @param string $ip The IP you want the domain to resolve to
	* @return mixed
	* @link http://docs.cpanel.net/twiki/bin/view/AllDocumentation/AutomationIntegration/AddDNSZone XML API Call documentation
	*/
	public function adddns($domain, $ip) {
		if (!isset($domain) || !isset($ip)) {
			error_log("adddns require that domain, ip are passed to it");
			return false;
		}
		return $this->xmlapi_query('adddns', array('domain' => $domain, 'ip' => $ip));
	}

	/**
	* Add a record to a zone
	*
	* This will append a record to a DNS Zone.  The $args argument to this function 
	* must be an associative array containing information about the DNS zone, please 
	* see the XML API Call documentation for more info
	*
	* @param string $zone The DNS zone that you want to add the record to
	* @param array $args Associative array representing the record to be added
	* @return mixed
	* @link http://docs.cpanel.net/twiki/bin/view/AllDocumentation/AutomationIntegration/AddZoneRecord XML API Call documentation
	*/
	public function addzonerecord( $zone, $args ) {
		if (!is_array($args)) {
			error_log("addzonerecord requires that $args passed to it is an array");
			return;
		}
		
		$args['zone'] = $zone;
		return $this->xmlapi_query('addzonerecord', $args);
	}

	/**
	* Edit a Zone Record
	*
	* This XML API Function will allow you to edit an existing DNS Zone Record.
	* This works by passing in the line number of the record you wish to edit.
	* Line numbers can be retrieved with dumpzone()
	*
	* @param string $zone The zone to edit
	* @param int $line The line number of the zone to edit
	* @param array $args An associative array representing the zone record
	* @return mixed
	* @link http://docs.cpanel.net/twiki/bin/view/AllDocumentation/AutomationIntegration/EditZoneRecord XML API Call documentation
	* @see dumpzone()
	*/
	
	public function editzonerecord( $zone, $line, $args ) {
		if (!is_array($args)) {
			error_log("editzone requires that $args passed to it is an array");
			return;
		}
		
		$args['domain'] = $zone;
		$args['Line'] = $line;
		return $this->xmlapi_query('editzonerecord', $args);
	}

	/**
	* Retrieve a DNS Record
	*
	* This function will return a data structure representing a DNS record, to 
	* retrieve all lines see dumpzone.
	* @param string $zone The zone that you want to retrieve a record from
	* @param string $line The line of the zone that you want to retrieve
	* @return mixed
	* @link http://docs.cpanel.net/twiki/bin/view/AllDocumentation/AutomationIntegration/GetZoneRecord XML API Call documentation
	*/
	public function getzonerecord( $zone, $line ) {
		return $this->xmlapi_query('getzonerecord', array( 'domain' => $zone, 'Line' => $line ) );
	}

	/**
	* Remove a DNS Zone
	*
	* This function will remove a DNS Zone from the server
	*
	* @param string $domain The domain to be remove
	* @return mixed
	* @link http://docs.cpanel.net/twiki/bin/view/AllDocumentation/AutomationIntegration/DeleteDNSZone XML API Call documentation
	*/
	public function killdns($domain) {
		if (!isset($domain)) {
			error_log("killdns requires that domain is passed to it");
			return false;
		}
		return $this->xmlapi_query('killdns', array('domain' => $domain));
	}

	/**
	* Return a List of all DNS Zones on the server
	* 
	* This XML API function will return an array containing all the DNS Zones on the server
	*
	* @return mixed
	* @link http://docs.cpanel.net/twiki/bin/view/AllDocumentation/AutomationIntegration/ListDNSZone XML API Call documentation
	*/
	public function listzones() {
		return $this->xmlapi_query('listzones');
	}

	/**
	* Return all records in a zone
	*
	* This function will return all records within a zone.
	* @param string $domain The domain to return the records from.
	* @return mixed
	* @link http://docs.cpanel.net/twiki/bin/view/AllDocumentation/AutomationIntegration/ListOneZone XML API Call documentation
	* @see editdnsrecord()
	* @see getdnsrecord()
	*/
	public function dumpzone($domain) {
		if (!isset($domain)) {
			error_log("dumpzone requires that a domain is passed to it");
			return false;
		}
		return $this->xmlapi_query('dumpzone', array('domain' => $domain));
	}
	
	/**
	* Return a Nameserver's IP
	*
	* This function will return a nameserver's IP
	*
	* @param string $nameserver The nameserver to lookup
	* @return mixed
	* @link http://docs.cpanel.net/twiki/bin/view/AllDocumentation/AutomationIntegration/LookupIP XML API Call documentation
	*/
	public function lookupnsip($nameserver) {
		if (!isset($nameserver)) {
			error_log("lookupnsip requres that a nameserver is passed to it");
			return false;
		}
		return $this->xmlapi_query('lookupnsip', array('nameserver' => $nameserver));
	}

	/**
	* Remove a line from a zone
	*
	* This function will remove the specified line from a zone
	* @param string $zone The zone to remove a line from
	* @param int $line The line to remove from the zone
	* @link http://docs.cpanel.net/twiki/bin/view/AllDocumentation/AutomationIntegration/RemoveZone XML API Call documentation
	*/
	public function removezonerecord($zone, $line) {
		if ( !isset($zone) || !isset($line) ) {
			error_log("removezone record requires that a zone and line number is passed to it");
			return false;
		}
		return $this->xmlapi_query('removezonerecord', array('zone' => $zone, 'Line' => $line) );
	}
	
	/**
	* Reset a zone
	*
	* This function will reset a zone removing all custom records.  Subdomain records will be readded by scanning the userdata datastore.
	* @param string $domain the domain name of the zone to reset
	* @return mixed
	* @link http://docs.cpanel.net/twiki/bin/view/AllDocumentation/AutomationIntegration/ResetZone XML API Call documentation
	*/
	public function resetzone($domain) {
		if ( !isset($domain) ) {
			error_log("resetzone requires that a domain name is passed to it");
			return false;
		}
		return $this->xmlapi_query('resetzone', array('domain' => $domain));
	}
	
	####
	# Server information
	####

	/**
	* Get a server's hostname
	*
	* This function will return a server's hostname
	* @return mixed
	* @link http://docs.cpanel.net/twiki/bin/view/AllDocumentation/AutomationIntegration/DisplayServerHostname XML API Call documentation
	*/
	public function gethostname() {
		return $this->xmlapi_query('gethostname');
	}

	/**
	* Get the version of cPanel running on the server
	*
	* This function will return the version of cPanel/WHM running on the remote system
	* @return mixed
	* @link http://docs.cpanel.net/twiki/bin/view/AllDocumentation/AutomationIntegration/DisplaycPanelWHMVersion XML API Call documentation
	*/
	public function version() {
		return $this->xmlapi_query('version');
	}


	/**
	* Get Load Average
	*
	* This function will return the loadavg of the remote system
	*
	* @return mixed
	* @link http://docs.cpanel.net/twiki/bin/view/AllDocumentation/AutomationIntegration/LoadAvg XML API Call documentation
	*/
	public function loadavg() {
		return $this->xmlapi_query('loadavg');
	}

	// This API function displays a list of all selected stats for a specific user.
	public function stat($username, $args = null) {
		if ( (!isset($username)) || (!isset($args)) ) {
			error_log("stat requires that a username and options are passed to it");
			return false;
		}
		if (is_array($args)) {
		$display = '';
			foreach($args as $key => $value){
				$display .= $value . '|';
			}
			$values['display'] = substr($display, 0, -1);
		}
		else {
			$values['display'] = substr($args, 0, -1);
		}
		return $this->api2_query($username, 'StatsBar', 'stat', $values);
	}

	/* WRAPPER FUNCTIONS 
	 * ================= */
	
	public function add_database($db_name)
	{
		return $this->api1_query($this->user, "Mysql", "adddb", array($db_name));   
	}

	public function add_database_user($db_user, $db_pass)
	{
		return $this->api1_query($this->user, "Mysql", "adduser", array($db_user, $db_pass));   
	}

	public function remove_database_user($db_user)
	{
		return $this->api1_query($this->user, "Mysql", "deluser", array($this->user."_".$db_user));
	}

	public function update_database_user($db_user, $new_password)
	{
		if ($this->remove_database_user($db_user))
		{
			return $this->add_database_user($db_user, $new_password);
		}

		return false;
	}

	// Privileges: "all" == "alter drop create delete insert update lock"
	public function attach_database_user($db_name, $db_user, $privileges = 'all')
	{
		return $this->api1_query($this->user, "Mysql", "adduserdb", array($this->user."_".$db_name, $this->user."_".$db_user, $privileges));
	}

	// Privileges: "all" == "alter drop create delete insert update lock"
	public function create_database($db_name, $db_user, $db_pass, $privileges = 'all')
	{
		if (!$this->add_database($db_name))
		{
			return false;
		}

		if (!$this->add_database_user($db_user, $db_pass))
		{
			return false;
		}

		if (!$this->attach_database_user($db_name, $db_user, $privileges))
		{
			return false;
		}

		return true;
	}

	public function remove_database($db_name)
	{
		return $this->api1_query($this->user, "Mysql", "deldb", array($this->user."_".$db_name));
	}

	public function remove_database_and_user($db_name, $db_user)
	{
		if (!$this->remove_database($db_name))
		{
			return false;
		}

		if (!$this->remove_database_user($db_user))
		{
			return false;
		}

		return true;
	}

	public function add_subdomain($domain, $rootdomain)
	{
		return $this->api1_query($this->user, "SubDomain", "addsubdomain", array($domain, $rootdomain));
	}
}