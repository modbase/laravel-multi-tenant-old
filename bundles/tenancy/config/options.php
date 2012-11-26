<?php

/* Tenancy Bundle Configuration
 * ============================ */

return array(
    'enable_cpanel' => false,           // Enable/disable cPanel features
	'cpanel_user' => 'your_cpanel',		// Your cPanel username
	'cpanel_pass' => 'your_pass',		// Your cPanel password
	'cpanel_host' => 'your_hostname',	// The hostname (or IP) where cPanel is located
	'cpanel_port' => 2083,				// This port should be ok by default
	'debug' => false,					// Display debug messages of the Cpanel class
);