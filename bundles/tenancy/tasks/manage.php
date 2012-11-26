<?php

class Tenancy_Manage_Task {

	protected $cp;
	protected $cpaneluser;
	protected $cpanel;

	public function __construct()
	{
		$this->cpanel = Config::get('tenancy::options.enable_cpanel');

		if ($this->cpanel)
		{
			$this->cpaneluser = Config::get('tenancy::options.cpanel_user');
			$this->cp = new Cpanel(Config::get('tenancy::options.cpanel_host'), $this->cpaneluser, Config::get('tenancy::options.cpanel_pass'));
			$this->cp->set_port(Config::get('tenancy::options.cpanel_port'));   
			$this->cp->set_debug(Config::get('tenancy::options.debug'));
		}
	}

	public function run($args = array())
	{
		echo "\nAvailable commands:\n\n";
		echo "manage:list_all\tList all available tenants.\n";
		echo "manage:add\tAdd a new tenant to the system.\n";
		echo "manage:remove\tRemoves a specific tenant from the system.\n";
		echo "manage:update\tUpdate the database password for this tenant.\n";
		echo "manage:reset\tSet a new random database password for this tenant.\n";
		return true;
	}

	public function list_all($args = array())
	{
		if ($tenants = opendir(path('tenants')))
		{	
			$exclude = array('default', '.', '..');
			$count = 0;

			echo "\nAvailable tenants:\n";
			while (($tenant = readdir($tenants)) !== false)
			{
				if (is_dir(path('tenants').$tenant) && !in_array($tenant, $exclude))
				{
					$count++;
					echo "- $tenant\n";
				}
			}

			closedir($tenants);

			if ($count === 0)
				echo "No tenants added yet";
		}
	}

	public function add($args = array())
	{
		if (count($args) === 0)
		{
			echo "Usage: php artisan tenancy::manage:add [tenant_name] [database_password]";
			return false;
		} else if (count($args) === 1) {
			// No password supplied, generate a random one
			$args[1] = Str::random(10);
		}

		list($name, $db_pass) = $args;
		$db_user = $db_name = $name;

		$this->message("Creating tenant folder structure... ");

		if (!$this->create_tenant_folder($name))
		{
			echo "ERROR! Could not create new tenant directory! Make sure this name is unique.";
			return false;
		}

		$this->message('ok!', true);	
		$this->message("Updating config files... ");

		$config = File::get(path('tenants').$name.'/config.php');
		$config = preg_replace("/'DB_NAME', '.*'/", "'DB_NAME', '{$db_name}'", $config);
		$config = preg_replace("/'DB_USER', '.*'/", "'DB_USER', '{$db_user}'", $config);
		$config = preg_replace("/'DB_PASS', '.*'/", "'DB_PASS', '{$db_pass}'", $config);
		File::put(path('tenants').$name.'/config.php', $config);

		$this->message('ok!', true);

		if ($this->cpanel)
		{
			$this->message("Creating database... ");

			if (!$this->cp->create_database($db_name, $db_user, $db_pass))
			{
				echo "ERROR! Could not create the database!";
				return false;
			}

			$this->message('ok!', true);

			// TODO: create a subdomain and link it to the tenants/$name/public folder
		}

		echo "DONE! New tenant ($name) added to the system.";
		return true;
	}

	public function reset($args = array())
	{
		if (count($args) != 1)
		{
			echo "Usage: php artisan tenancy::manage:reset [tenant_name]";
			return false;
		}

		$new_pass = Str::random(10);

		return $this->update_password(array($args[0], $new_pass));
	}

	public function update($args = array())
	{
		if (count($args) != 2)
		{
			echo "Usage: php artisan tenancy::manage:update [tenant_name] [new_password]";
			return false;
		}

		list($name, $new_pass) = $args;

		if (!file_exists(path('tenants').$name))
		{
			echo "ERROR! This tenant does not exist!";
			return false;
		}	

		if ($this->cpanel)
		{
			$this->message("Updating database user... ");

			if (!$this->cp->update_database_user($name, $new_pass))
			{
				echo "ERROR! Could not update the database user!";
				return false;
			}

			$this->message('ok!', true);
		}

		$this->message("Updating config files... ");
		$config = File::get(path('tenants').$name.'/config.php');
		$config = preg_replace("/'DB_PASS', '.*'/", "'DB_PASS', '{$new_pass}'", $config, 1, $count);

		if ($count != 1)
		{
			echo "ERROR! Could not update the password!";
			return false;
		}

		File::put(path('tenants').$name.'/config.php', $config);
		$this->message('ok!', true);

		echo "DONE! Tenant ($name) password has been updated.";
		return true;
	}

	public function remove($args = array())
	{
		if (count($args) != 1)
		{
			echo "Usage: php artisan tenancy::manage:remove [tenant_name]";
			return false;
		}

		$name = $args[0];

		$this->message("Removing tenant directory... ");

		if (!file_exists(path('tenants').$name))
		{
			echo "ERROR! This tenant does not exist!";
			return false;
		}

		File::rmdir(path('tenants').$name);

		$this->message('ok!', true);

		if ($this->cpanel)
		{
			$this->message("Removing database and user... ");

			if (!$this->cp->remove_database_and_user($name, $name))
			{
				echo "ERROR! Could not remove database!";
				return false;
			}

			$this->message('ok!', true);

			// TODO: remove subdomain
		}

		echo "DONE! Tenant ($name) is removed from the system.";
		return true;
	}

	private function create_tenant_folder($name)
	{
		if (file_exists(path('tenants').$name))
		{
			// Tenant already exists
			return false;
		}
		
		return File::cpdir(path('tenants').'default', path('tenants').$name);
	}

	private function message($msg, $newline = false)
	{
		echo $msg.($newline ? "\n" : "");
		flush();
		ob_flush();
	}
}