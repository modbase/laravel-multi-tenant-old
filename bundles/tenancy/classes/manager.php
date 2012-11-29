<?php namespace Tenancy;

use Exception;
use Laravel\Config;
use Laravel\File;
use \DB;

class Manager
{
	/**
	 * cPanel instance.
	 * 
	 * @var	object
	 */
	protected static $cp;
	
	/**
	 * cPanel username.
	 * 
	 * @var string
	 */
	protected static $cpaneluser;
	
	/**
	 * cPanel option.
	 * 
	 * @var	bool
	 */
	protected static $cpanel;

	/**
	 * Constructor
	 * 
	 * @return	void
	 */
	public function __construct()
	{
		static::$cpanel = Config::get('tenancy::options.enable_cpanel');

		// If cPanel is enabled (via config), then setup a new instance of the Cpanel class.
		if (static::$cpanel)
		{
			static::$cpaneluser = Config::get('tenancy::options.cpanel_user');
			static::$cp = new Cpanel(Config::get('tenancy::options.cpanel_host'), $this->cpaneluser, Config::get('tenancy::options.cpanel_pass'));
			static::$cp->set_port(Config::get('tenancy::options.cpanel_port'));   
			static::$cp->set_debug(Config::get('tenancy::options.debug'));
		}
	}

	/**
	 * List all tenants on system.
	 * 
	 * <code>
	 * 		Tenancy\Manager::show();
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
	 * 		Tenancy\Manager::make(<tenant_name>, <db_pass>);
	 * </code>
	 * 
	 * @param 	string 	$name
	 * @param 	string 	$db_pass
	 * @return 	bool
	 */
	public static function make($name, $db_pass)
	{
		if (!preg_match('/^[a-z0-9]+$/', $name))
		{
			throw new Exception("ERROR! The tenant name provided contains illegal characters!");
			return false;
		}

		$db_user = $db_name = Config::get('tenancy::options.db_prefix').$name;

		if (!self::create_tenant_folder($name))
		{
			return false;
		}

		if (!self::create_tenant_connection($name, $db_pass))
		{
			return false;
		}

		// If using cPanel then create the database using the cPanel API
		// otherwise we will use Laravel's built in DB class.
		if (static::$cpanel)
		{
			if (!static::$cp->create_database($db_name, $db_user, $db_pass))
			{
				throw new Exception("ERROR! Could not create the database!");
				return false;
			}

			// TODO: create a subdomain via cPanel API and link it to the tenants/$name/public folder
		}
		else
		{
			if (!DB::query("CREATE DATABASE $db_name"))
			{
				throw new Exception("ERROR! Could not create the database!");
				return false;
			}
		}

		return true;
	}

	/**
	 * Reset tenant password.
	 * 
	 * <code>
	 * 		Tenancy\Manager::reset(<tenant_name>);
	 * </code>
	 * 
	 * @param 	string 	$name
	 * @return 	bool
	 */
	public static function reset($name)
	{
		// Need to write this properly.
	}

	/**
	 * Update tenant password.
	 * 
	 * <code>
	 * 		Tenancy\Manager::update(<tenant_name>, <db_pass>);
	 * </code>
	 * 
	 * @param 	string 	$name
	 * @param 	string 	$db_pass
	 */
	public static function update($name, $db_pass)
	{
		$db_user = Config::get('tenancy::options.db_prefix').$name;

		if (!file_exists(path('tenants').$name))
		{
			throw new Exception("ERROR! This tenant does not exist!");
			return false;
		}	

		if (static::$cpanel)
		{
			if (!static::$cp->update_database_user($db_user, $new_pass))
			{
				throw new Exception("ERROR! Could not update the tenant datbase connection!");
				return false;
			}
		}
		else
		{
			throw new Exception("ERROR! You cannot update the tenant's password unless you're using cPanel!");
			return false;
		}

		$config = File::get(path('tenants').$name.'/config.php');
		$config = preg_replace("/'DB_PASS', '.*'/", "'DB_PASS', '{$new_pass}'", $config, 1, $count);

		if ($count !== 1)
		{
			throw new Exception("ERROR! Could not update the password!");
			return false;
		}

		File::put(path('tenants').$name.'/config.php', $config);

		return true;
	}

	/**
	 * Remove tenant from the system.
	 * 
	 * <code>
	 * 		Tenancy\Manager::remove(<tenants>);
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
		
		if ($name == 'default')
		{
			throw new Exception("ERROR! You cannot delete the default tenant!");
		}
			
		static::remove_tenant_folder($name);
		static::remove_tenant_connection($name);

		$db_name = $db_user = Config::get('tenancy::options.db_prefix').$name;
			
		if (static::$cpanel)
		{
			if (!static::$cp->remove_database_and_user($db_name, $db_user))
			{
				throw new Exception("ERROR! Could not remove database!");
			}

			// TODO: remove subdomain via cPanel API
		}
		else
		{
			if (!DB::query("DROP DATABASE $db_name"))
			{
				throw new Exception("ERROR! Could not drop the database!");
			}
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
		if (file_exists(path('tenants').$name))
		{
			throw new Exception("ERROR! Could not create new tenant directory '$name'! Make sure this name is unique.");
			return false;
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
		if (!file_exists(path('tenants').$name))
		{
			throw new Exception("ERROR! Directory for '$name' does not exist!");
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
	private static function create_tenant_connection($name = null, $db_pass = null)
	{
		// If we don't have a name or a password we can't continue
		if (is_null($name) || is_null($db_pass))
		{
			throw new Exception("ERROR! Tenant name or password was not provided!");
			return false;
		}

		$tenants = Config::get('tenancy::tenants', array());

		// Obviously we don't want to override somebody's connection
		// so we check to see if the tenant name already exists in
		// the connections list.
		if (array_key_exists($name, $tenants))
		{
			throw new Exception("ERROR! '$name' already exists in the tenant connections list!");
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
			'database'	=> Config::get('tenancy::options.db_prefix').$name,
			'username'	=> Config::get('tenancy::options.db_prefix').$name,
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

		return File::put(path('bundle').'tenancy/config/tenants.php', $content);
	}

	/**
	 * Remove tenant database connection from /config/tenants.php
	 * 
	 * @param 	string 	$name
	 * @return 	bool
	 */
	private static function remove_tenant_connection($name)
	{
		$tenants = Config::get('tenancy::tenants');

		if (!array_key_exists($name, $tenants))
		{
			throw new Exception("ERROR: couldn't find '$name' in tenant connections list!");
			return false;
		}

		unset($tenants[$name]);
		$tenants = var_export($tenants, true);

		$file_open = File::get(path('bundle').'tenancy/config/tenants.php');
		$content   = preg_replace(

			// Find the array between our markers. #BEGIN_... and #END_...
			"/(#BEGIN_TENANTS_LIST)(.*?)(#END_TENANTS_LIST)/is",

			// Replace the entire array with the updated list. We also need to
			// put in new markers because the expression we use removes them.
			"#BEGIN_TENANTS_LIST\n$tenants;\n#END_TENANTS_LIST",

			$file_open
		);

		return File::put(path('bundle').'tenancy/config/tenants.php', $content);
	}

}