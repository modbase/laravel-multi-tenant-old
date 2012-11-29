<?php

class Manager
{
	/**
	 * List all tenants on system.
	 * 
	 * <code>
	 * 		Manager::show();
	 * </code>
	 * 
	 * @return 	array
	 */
	public static function show()
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

			if ($count === 0)
			{
				throw new Exception('No tenants added yet!');
				return false;
			}
		}
		else
		{
			throw new Exception('There was a problem opening the tenants directory.');
			return false;
		}
	}

	/**
	 * Add new tenant to the system.
	 * 
	 * <code>
	 * 		Manager::make(<tenant_name>, <db_pass>);
	 * </code>
	 * 
	 * @param 	string 	$name
	 * @param 	string 	$db_pass
	 * @return 	bool
	 */
	public static function make($name, $db_pass)
	{
		if ( ! preg_match('/^[a-z0-9]+$/', $name) )
		{
			throw new Exception("ERROR! The tenant name provided contains illegal characters!");
			return false;
		}

		$db_user = $db_name = Config::get('tenancy::options.db_prefix').$name;

		if (!static::create_tenant_folder($name))
		{
			return false;
		}

		$config = File::get(path('tenants').$name.'/config.php');	
		$config = preg_replace("/'DB_NAME', '.*'/", "'DB_NAME', '{$db_name}'", $config);	
		$config = preg_replace("/'DB_USER', '.*'/", "'DB_USER', '{$db_user}'", $config); 	
		$config = preg_replace("/'DB_PASS', '.*'/", "'DB_PASS', '{$db_pass}'", $config);

		File::put(path('tenants').$name.'/config.php', $config);

		if (!DB::query("CREATE DATABASE $db_name"))
		{
			throw new Exception("ERROR! Could not create the database!");
			return false;
		}

		return true;
	}

	/**
	 * Reset tenant password.
	 * 
	 * <code>
	 * 		Manager::reset(<tenant_name>);
	 * </code>
	 * 
	 * @param 	string 	$name
	 * @return 	bool
	 */
	
	public static function reset($name)
	{
		// TODO: reset password for database user
		throw new Exception('Not implemented yet, sorry!');
	}

	/**
	 * Update tenant password.
	 * 
	 * <code>
	 * 		Manager::update(<tenant_name>, <db_pass>);
	 * </code>
	 * 
	 * @param 	string 	$name
	 * @param 	string 	$db_pass
	 */
	public static function update($name, $db_pass)
	{
		// TODO: update password for database user
		throw new Exception('Not implemented yet, sorry!');
	}

	/**
	 * Remove tenant from the system.
	 * 
	 * <code>
	 * 		Manager::remove(<tenants>);
	 * </code>
	 * 
	 * @param 	mixed 	$name
	 * @return 	bool
	 */
	public static function remove($name)
	{
		if (is_array($name))
		{
			foreach ($name as $tenant)
			{
				static::remove($tenant);
			}
			return;
		}
			
		static::remove_tenant_folder($name);

		$db_name = Config::get('tenancy::options.db_prefix').$name;
			
		if ( ! DB::query("DROP DATABASE $db_name") )
		{
			throw new Exception("ERROR! Could not drop the database!");
		}

		return true;
	}

	/**
	 * Create tenant foler in /tenants directory based on the default.
	 * 
	 * @param	string	$name
	 * @return	bool
	 */
	private static function create_tenant_folder($name)
	{
		if ( file_exists(path('tenants').$name) )
		{
			throw new Exception("ERROR! Could not create new tenant directory '$name'! Make sure this name is unique.");
		}
		
		return File::cpdir(path('tenants').'default', path('tenants').$name);
	}

	/**
	 * Remove tenant folder from /tenants directory.
	 * 
	 * @param 	string 	$name
	 * @return 	bool
	 */
	private static function remove_tenant_folder($name)
	{
		if ($name == 'default')
		{
			throw new Exception("ERROR! You cannot remove the default tenant!");
		}

		if ( ! file_exists(path('tenants').$name) )
		{
			throw new Exception("ERROR! Directory for '$name' does not exist!");
			return false;
		}
	
		return File::rmdir(path('tenants').$name);
	}
}