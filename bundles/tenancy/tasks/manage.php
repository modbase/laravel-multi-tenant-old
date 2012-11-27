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
		$message = "\nAvailable commands:\n\n
		manage:list_all\tList all available tenants.\n
		manage:add\tAdd a new tenant to the system.\n
		manage:remove\tRemoves a specific tenant from the system.\n
		manage:update\tUpdate the database password for this tenant.\n
		manage:reset\tSet a new random database password for this tenant.\n";
		
		$this->message($message);
		
		return true;
	}

	/**
	 * List all tenants on system.
	 * 
	 * <code>
	 * 	php artisan tenancy::manage:list_all
	 * </code>
	 * 
	 * @param	array	$args
	 * @return	bool
	 */
	public function list_all($args = array())
	{
		// If we can't open the directory there isn't anything we can do now.
		if ($tenants = opendir(path('tenants')))
		{
			// List of places we don't want to display
			$exclude = array('default', '.', '..');
			$count = 0;

			$this->message('Available Tenants:', true);
			while (($tenant = readdir($tenants)) !== false)
			{
				// Check whether the tenant is actually a directory
				// and that it isn't something we don't want to display.
				if (is_dir(path('tenants').$tenant) && !in_array($tenant, $exclude))
				{
					$count++;
					$this->message("- $tenant", true);
				}
			}

			closedir($tenants);

			if ($count === 0) $this->message('No tenants added yet.');
		}
		else
		{
			$this->message("There was a problem opening the tenants directory.");
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
	 * @param	array	$args
	 * @return	bool
	 */
	public function add($args = array())
	{
		// If we don't have any arguments then tell the user how to
		// properly format the command.
		if (count($args) === 0)
		{
			$this->message('Usage: php artisan tenancy::manage:add [tenant_name] [database_password]');
			return false;
		}
		else if (count($args) === 1)
		{
			// No password supplied, generate a random one
			$args[1] = Str::random(10);
		}

		list($name, $db_pass) = $args;
		$db_user = $db_name = $name;

		$this->message("Creating tenant folder structure... ");

		if (!$this->create_tenant_folder($name))
		{
			$this->message('ERROR: Could not create new tenant directory! Make sure this name is unique.');
			return false;
		}

		$this->message('ok!', true);	
		$this->message("Updating config files... ");
		
		// Grab the default config file from tenants/default/
		// Replace the default values with the prepared values.
		$config = File::get(path('tenants').$name.'/config.php');
		$config = preg_replace("/'DB_NAME', '.*'/", "'DB_NAME', '{$db_name}'", $config);
		$config = preg_replace("/'DB_USER', '.*'/", "'DB_USER', '{$db_user}'", $config);
		$config = preg_replace("/'DB_PASS', '.*'/", "'DB_PASS', '{$db_pass}'", $config);
		File::put(path('tenants').$name.'/config.php', $config);

		$this->message('ok!', true);

		// If using cPanel then create the database using the cPanel API
		// otherwise we will use Laravel's built in DB class.
		if ($this->cpanel)
		{
			$this->message("Creating database... ");

			if (!$this->cp->create_database($db_name, $db_user, $db_pass))
			{
				$this->message('Error: Could not create the database!');
				return false;
			}

			$this->message('ok!', true);

			// TODO: create a subdomain via cPanel API and link it to the tenants/$name/public folder
		}
		else
		{
			if (!DB::query("CREATE DATABASE $db_name"))
			{
				$this->message('Error: Could not create the database!');
				return false;
			}
		}

		$this->message("Done! New tenant ($name) added to the system.");
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
		if (count($args) != 1)
		{
			$this->message('Usage: php artisan tenancy::manage:reset [tenant_name]');
			return false;
		}

		$new_pass = Str::random(10);

		return $this->update_password(array($args[0], $new_pass));
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
		if (count($args) != 2)
		{
			$this->message('Usage: php artisan tenancy::manage:update [tenant_name] [tenant_password]');
			return false;
		}

		list($name, $new_pass) = $args;

		if (!file_exists(path('tenants').$name))
		{
			$this->message("Error: Tenant ($name) does not exist!");
			return false;
		}	

		if ($this->cpanel)
		{
			$this->message("Updating database user... ");

			if (!$this->cp->update_database_user($name, $new_pass))
			{
				$this->message('Error: Could not update the database user!');
				return false;
			}

			$this->message('ok!', true);
		}

		$this->message("Updating config files... ");
		$config = File::get(path('tenants').$name.'/config.php');
		$config = preg_replace("/'DB_PASS', '.*'/", "'DB_PASS', '{$new_pass}'", $config, 1, $count);

		if ($count != 1)
		{
			$this->message('Error: Could not update the password!');
			echo "ERROR! Could not update the password!";
			return false;
		}

		File::put(path('tenants').$name.'/config.php', $config);
		$this->message('ok!', true);

		$this->message("Done! Tenant ($name) password has been updated.");
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
			$this->message('Usage: php artisan tenancy::manage:remove [tenant_name]');
			return false;
		}

		foreach ($args as $name)
		{
			if ($name == 'default')
			{
				$this->message('Error: You cannot delete the default tenant!');
				continue;
			}
			
			$this->message("Removing tenant directory... ");
	
			if (!file_exists(path('tenants').$name))
			{
				$this->message('Error: This tenant does not exist!');
				continue;
			}
	
			File::rmdir(path('tenants').$name);
	
			$this->message('ok!', true);
	
			if ($this->cpanel)
			{
				$this->message("Removing database and user... ");
	
				if (!$this->cp->remove_database_and_user($name, $name))
				{
					$this->message("Error: Could not remove the database ($name)!");
					continue;
				}
	
				$this->message('ok!', true);
	
				// TODO: remove subdomain
			}
			else
			{
				if (!DB::query("DROP DATABASE $name"))
				{
					$this->message("Error: Could not drop the database ($name)!");
					return false;
				}
			}
			
			$this->message("Done! Tenant ($name) has been removed from the system.");
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
			// Tenant already exists
			return false;
		}
		
		return File::cpdir(path('tenants').'default', path('tenants').$name);
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
		echo $msg.($newline ? "\n" : "");
		flush();
		ob_flush();
	}
}