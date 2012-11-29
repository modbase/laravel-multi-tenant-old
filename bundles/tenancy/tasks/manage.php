<?php

class Tenancy_Manage_Task {

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
	public function show()
	{
		try
		{
			Tenancy\Manager::show();
		}
		catch (Exception $e)
		{
			echo $e->getMessage();
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

		try
		{
			Tenancy\Manager::make($name, $db_pass);
			echo "DONE! New tenant ($name) added to the system.";
		}
		catch (Exception $e)
		{
			echo $e->getMessage();
		}
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

		$name     = $args[0];
		$new_pass = Str::random(10);

		try
		{
			Tenancy\Manager::update($name, $new_pass);
		}
		catch (Exception $e)
		{
			echo $e->getMessage();
		}
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

		try
		{
			Tenancy\Manager::update($name, $new_pass);
			echo "DONE! Tenant ($name) password has been updated.";
		}
		catch (Exception $e)
		{
			echo $e->getMessage();
		}
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

		try
		{
			Tenancy\Manager::remove($args);
			echo "DONE! Tenant(s) removed from the system.";
		}
		catch (Exception $e)
		{
			echo $e->getMessage();
		}
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