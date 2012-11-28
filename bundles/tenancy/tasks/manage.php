<?php

class Tenancy_Manage_Task {

	/**
	 * cPanel instance.
	 * 
	 * @var	object
	 */
	protected $cp;
	
	/**
	 * cPanel username.
	 * 
	 * @var string
	 */
	protected $cpaneluser;
	
	/**
	 * cPanel option.
	 * 
	 * @var	bool
	 */
	protected $cpanel;

	/**
	 * Constructor
	 * 
	 * @return	void
	 */
	public function __construct()
	{
		$this->cpanel = Config::get('tenancy::options.enable_cpanel');

		// If cPanel is enabled (via config), then setup a new instance of the Cpanel class.
		if ($this->cpanel)
		{
			$this->cpaneluser = Config::get('tenancy::options.cpanel_user');
			$this->cp = new Cpanel(Config::get('tenancy::options.cpanel_host'), $this->cpaneluser, Config::get('tenancy::options.cpanel_pass'));
			$this->cp->set_port(Config::get('tenancy::options.cpanel_port'));   
			$this->cp->set_debug(Config::get('tenancy::options.debug'));
		}
	}

	/**
	 * List available artisan commands.
	 * 
	 * <code>
	 * 	php artisan tenancy::manage
	 * </code>
	 * 
	 * @param	array	$args
	 * @return	void
	 */
	public function run($args = array())
	{
		echo PHP_EOL.'Available commands:'.PHP_EOL.PHP_EOL;
		echo "manage:show\tList all available tenants.".PHP_EOL;
		echo "manage:add\tAdd a new tenant to the system.".PHP_EOL;
		echo "manage:remove\tRemoves a specific tenant from the system.".PHP_EOL;
		echo "manage:update\tUpdate the database password for this tenant.".PHP_EOL;
		echo "manage:reset\tSet a new random database password for this tenant.".PHP_EOL;
		
		return true;
	}

	/**
	 * List all tenants on system.
	 * 
	 * <code>
	 * 	php artisan tenancy::manage:show
	 * </code>
	 * 
	 * @param	array	$args
	 * @return	bool
	 */
	public function show($args = array())
	{
		// If we can't open the directory there isn't anything we can do now.
		if ($tenants = opendir(path('tenants')))
		{
			// List of places we don't want to display
			$exclude = array('default', '.', '..');
			$count = 0;

			echo PHP_EOL.'Available tenants:'.PHP_EOL;
			while (($tenant = readdir($tenants)) !== false)
			{
				// Check whether the tenant is actually a directory
				// and that it isn't something we don't want to display.
				if (is_dir(path('tenants').$tenant) && !in_array($tenant, $exclude))
				{
					$count++;
					echo "- $tenant".PHP_EOL;
				}
			}

			closedir($tenants);

			if ($count === 0) echo 'No tenants added yet';
		}
		else
		{
			$this->message('There was a problem opening the tenants directory.');
			return false;
		}
	}

	/**
	 * Add a new tenant.
	 * 
	 * <code>
	 * 	php artisan tenancy::manage:add <tenant_name> [<db_pass>]
	 * </code>
	 *
	 * If cPanel is not enabled via the options.php, then the
	 * database information of tenants/default/config.php will be used.
	 * 
	 * @param	array	$args
	 * @return	bool
	 */
	public function add($args = array())
	{
		// If we don't have any arguments then tell the user how to
		// properly format the command.
		if (count($args) === 0)
		{
			echo 'Usage: php artisan tenancy::manage:add <tenant_name> [<db_pass>]';
			return false;
		}
		else if (count($args) === 1)
		{
			// No password supplied, generate a random one
			$args[1] = Str::random(10);
		}

		list($name, $db_pass) = $args;
		$db_user = $db_name = Config::get('tenancy::options.db_prefix').$name;

		$this->message('Creating tenant folder structure... ');

		if (!$this->create_tenant_folder($name))
		{
			return false;
		}

		$this->message('ok!', true);

		$this->message('Updating config file...');
		if (!$this->create_tenant_connection($name, $db_pass))
		{
			return false;
		}
		$this->message('ok!', true);

		$this->message('Creating database... ');

		// If using cPanel then create the database using the cPanel API
		// otherwise we will use Laravel's built in DB class.
		if ($this->cpanel)
		{
			if (!$this->cp->create_database($db_name, $db_user, $db_pass))
			{
				echo 'ERROR! Could not create the database!';
				return false;
			}

			$this->message('ok!', true);

			// TODO: create a subdomain via cPanel API and link it to the tenants/$name/public folder
		}
		else
		{
			if (!DB::query("CREATE DATABASE $db_name"))
			{
				echo 'ERROR: Could not create the database!';
				return false;
			}
		}

		echo "DONE! New tenant ($name) added to the system.";
		return true;
	}

	/**
	 * Reset tenant password.
	 * 
	 * <code>
	 * 	php artisan tenancy::manage:reset <tenant_name>
	 * </code>
	 * 
	 * @param	array	$args
	 * @return	void
	 */
	public function reset($args = array())
	{
		// If we don't have any arguments then tell the user how to
		// properly format the command.
		if (count($args) !== 1)
		{
			echo 'Usage: php artisan tenancy::manage:reset <tenant_name>';
			return false;
		}

		$new_pass = Str::random(10);

		return $this->update(array($args[0], $new_pass));
	}

	/**
	 * Update tenant password.
	 * 
	 * <code>
	 * 	php artisan tenancy::manage:update <tenant_name> <new_db_pass>
	 * </code>
	 * 
	 * @param	array	$args
	 * @return	bool
	 */
	public function update($args = array())
	{
		// If we don't have any arguments then tell the user how to
		// properly format the command.
		if (count($args) !== 2)
		{
			echo 'Usage: php artisan tenancy::manage:update <tenant_name> <new_db_pass>';
			return false;
		}

		list($name, $new_pass) = $args;
		$db_user = Config::get('tenancy::options.db_prefix').$name;

		if (!file_exists(path('tenants').$name))
		{
			echo 'ERROR! This tenant does not exist!';
			return false;
		}	

		if ($this->cpanel)
		{
			$this->message('Updating database user... ');

			if (!$this->cp->update_database_user($db_user, $new_pass))
			{
				echo 'ERROR! Could not update the database user!';
				return false;
			}

			$this->message('ok!', true);
		}

		$this->message('Updating config files... ');
		$config = File::get(path('tenants').$name.'/config.php');
		$config = preg_replace("/'DB_PASS', '.*'/", "'DB_PASS', '{$new_pass}'", $config, 1, $count);

		if ($count !== 1)
		{
			echo 'ERROR! Could not update the password!';
			return false;
		}

		File::put(path('tenants').$name.'/config.php', $config);
		$this->message('ok!', true);

		echo "DONE! Tenant ($name) password has been updated.";
		return true;
	}

	/**
	 * Remove tenant from system.
	 * 
	 * <code>
	 * 		// Remove a single tenant
	 * 		php artisan tenancy::manage:remove <tenant_name>
	 * 
	 * 		// Remove multiple tenants
	 * 		php artisan tenancy::manage:remove <tenant_name> <tenant2_name> <etc>
	 * </code>
	 * 
	 * @param	array	$args
	 * @return	bool
	 */
	public function remove($args = array())
	{
		// If we don't have any arguments tell the user how to
		// properly format the command.
		if (count($args) < 1)
		{
			echo 'Usage: php artisan tenancy::manage:remove <tenant_name>';
			return false;
		}

		foreach ($args as $name)
		{
			if ($name == 'default')
			{
				echo 'ERROR! You cannot delete the default tenant!';
				continue;
			}
			
			$this->message('Removing tenant directory... ');
			if (!$this->remove_tenant_folder($name))
			{
				continue;
			}
			$this->message('ok!', true);

			$db_name = $db_user = Config::get('tenancy::options.db_prefix').$name;
			
			if ($this->cpanel)
			{	
				$this->message('Removing database and user... ');

				if (!$this->cp->remove_database_and_user($db_name, $db_user))
				{
					echo 'ERROR! Could not remove database!';
					continue;
				}
	
				$this->message('ok!', true);
	
				// TODO: remove subdomain via cPanel API
			}
			else
			{
				$this->message('Removing database... ');

				if (!DB::query("DROP DATABASE $db_name"))
				{
					echo 'ERROR: Could not drop the database!';
					continue;
				}
			}
			
			echo "DONE! Tenant ($name) is removed from the system.";
		}
		return true;
	}

	/**
	 * Create tenant foler in /tenants directory based on the default.
	 * 
	 * @param	string	$name
	 * @return	bool
	 */
	private function create_tenant_folder($name)
	{
		if (file_exists(path('tenants').$name))
		{
			echo 'ERROR! Could not create new tenant directory! Make sure this name is unique.';
			return false;
		}
		
		return File::cpdir(path('bundle').'/tenancy/tenants/default', path('tenants').$name);
	}

	/**
	 * Remove tenant folder from /tenants directory.
	 * 
	 * @param 	string 	$name
	 * @return 	bool
	 */
	private function remove_tenant_folder($name)
	{
		if (!file_exists(path('tenants').$name))
		{
			echo "ERROR! Directory for ($name) does not exist!";
			return false;
		}
	
		return File::rmdir(path('tenants').$name);
	}

	/**
	 * Add tenant database connection to /config/tenants.php
	 * 
	 * @param 	string 	$name
	 * @param 	string 	$db_pass
	 * @return 	bool
	 */
	private function create_tenant_connection($name = null, $db_pass = null)
	{
		// If we don't have a name or a password we can't continue
		if (is_null($name) || is_null($db_pass))
		{
			echo "ERROR: We need more to work with here! Not all the data got through.";
			return false;
		}

		$tenants = Config::get('tenancy::tenants', array());

		// Obviously we don't want to override somebody's connection
		// so we check to see if the tenant name already exists in
		// the connections list.
		if (array_key_exists($name, $tenants))
		{
			echo "ERROR: ($name) already has a database connection!";
			return false;
		}

		// Build the tenant connection array. 
		// Note: we prefix the database name with 'tenant_'. This makes
		// things appear tidier in phpmyadmin.
		// 
		// Todo: We need to come up with a way to use different database types.
		$tenant[$name] = array(
			'driver'	=> 'mysql',
			'host'		=> 'localhost',
			'database'	=> "tenant_$name",
			'username'	=> $name,
			'password'	=> $db_pass,
			'charset'	=> 'utf8',
			'prefix'	=> ''
		);
	
		// In order to push the updated tenants list to the config file we
		// convert it to a string using var_export($array, true). Then it's
		// pushed to the new file.
		$tenants = var_export(array_merge($tenant, $tenants), true);

		$file_open = File::get(path('bundle').'tenancy/config/tenants.php');
		$content   = preg_replace(

			// Find the array between our markers. #BEGIN_... and #END_...
			"/(#BEGIN_TENANTS_LIST)(.*?)(#END_TENANTS_LIST)/is",

			// Replace the entire array with the updated list. We also need to
			// put in new markers because the expression we use removes them.
			"#BEGIN_TENANTS_LIST\n$tenants;\n#END_TENANTS_LIST",

			$file_open
		);

		File::put(path('bundle').'tenancy/config/tenants.php', $content);
		return true;
	}

	/**
	 * Remove tenant database connection from /config/tenants.php
	 * 
	 * @param 	string 	$name
	 * @return 	bool
	 */
	private function remove_tenant_connection($name)
	{
		$tenants = Config::get('tenancy::tenants');

		if (!array_key_exists($name, $tenants))
		{
			echo "ERROR: ($name) doesn't have a database connection!";
			return false;
		}

		$tenants = var_export(unset($tenants[$name]));

		$file_open = File::get(path('bundle').'tenancy/config/tenants.php');
		$content   = preg_replace(

			// Find the array between our markers. #BEGIN_... and #END_...
			"/(#BEGIN_TENANTS_LIST)(.*?)(#END_TENANTS_LIST)/is",

			// Replace the entire array with the updated list. We also need to
			// put in new markers because the expression we use removes them.
			"#BEGIN_TENANTS_LIST\n$tenants;\n#END_TENANTS_LIST",

			$file_open
		);

		File::put(path('bundle').'tenancy/config/tenants.php', $content);

		return true;
	}

	/**
	 * Echo out a message without buffering it.
	 * 
	 * @param	string	$msg
	 * @param	bool	$newline
	 * @return	string
	 */
	private function message($msg, $newline = false)
	{
		echo $msg.($newline ? PHP_EOL : '');
		flush();
		ob_flush();
	}
}