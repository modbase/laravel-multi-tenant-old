<?php

/* Tenancy Bundle Configuration
 * ============================ */

return array(
    'enable_cpanel' => false,           // Enable/disable cPanel features.
	'cpanel_user' => 'your_cpanel',		// Your cPanel username. Leave empty if enable_cpanel set to false.
	'cpanel_pass' => 'your_pass',		// Your cPanel password. Leave empty if enable_cpanel set to false.
	'cpanel_host' => 'your_hostname',	// The hostname (or IP) where cPanel is located. Leave empty if enable_cpanel set to false.
	'cpanel_port' => 2083,				// This port should be ok by default. Don't touch this if enable_cpanel set to false.
	'debug' => false,					// Display debug messages of the Cpanel class. Don't touch this if enable_cpanel set to false.
	'db_prefix' => 'lv_',				// A database prefix, i.e. database name & username will be {db_prefix}{tenant name}. Leave empty if not required.
);