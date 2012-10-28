<?php

class Tenant_Task {

	protected $cp;
	protected $cpaneluser;

	public function __construct()
	{
		error_reporting(0);
		$this->cpaneluser = 'joostd';
		$this->cp = new Cpanel('modbase.be');
		$this->cp->set_port(2083);   
		$this->cp->password_auth($this->cpaneluser, 'YxcFkg14j8qSM');
		$this->cp->set_debug(0);
	}

	public function run($args = array())
	{
		echo "\nAvailable commands:\n\n";
		echo "tenant:add\t\tAdd a new tenant to the system.\n";
		echo "tenant:remove\t\tRemoves a specific tenant from the system.\n";
		echo "tenant:update_password\tUpdate the database password for this tenant.\n";
		echo "tenant:reset_password\tSet a new random database password for this tenant.\n";
		return false;
	}

	public function add($args = array())
	{
		if (count($args) != 2)
		{
			echo "Usage: php artisan tenant:add [name] [database_password]";
			return false;
		}

		list($name, $db_pass) = $args;
		$db_name = $name;
		$db_user = $name;

		$this->message("Creating tenant folder structure... ");
		if (!$this->create_tenant_folder($name))
		{
			echo "ERROR! Could not create new tenant directory! Make sure this name is unique.";
			return false;
		}
		$this->message('ok!', true);

		$this->message("Creating database... ");
		if (!$this->cp->create_database($db_name, $db_user, $db_pass))
		{
			echo "ERROR! Could not create the database!";
			return false;
		}
		$this->message('ok!', true);

		// Setup the config.php file which contains the database info
		$this->message("Updating config files... ");
		$config = File::get(path('tenants').$name.'/config.php');
		$config = preg_replace("/'DB_NAME', '.*'/", "'DB_NAME', '{$db_name}'", $config);
		$config = preg_replace("/'DB_USER', '.*'/", "'DB_USER', '{$db_user}'", $config);
		$config = preg_replace("/'DB_PASS', '.*'/", "'DB_PASS', '{$db_pass}'", $config);
		File::put(path('tenants').$name.'/config.php', $config);
		$this->message('ok!', true);

		// TODO: create a subdomain and link it to the tenants/$name/public folder

		echo "DONE! New tenant ($name) added to the system.";
		return true;
	}

	public function reset_password($args = array())
	{
		if (count($args) != 1)
		{
			echo "Usage: php artisan tenant:reset_password [name]";
			return false;
		}

		$new_pass = Str::random(10);

		return $this->update_password(array($args[0], $new_pass));
	}

	public function update_password($args = array())
	{
		if (count($args) != 2)
		{
			echo "Usage: php artisan tenant:update_password [name] [new_password]";
			return false;
		}

		list($name, $new_pass) = $args;

		if (!file_exists(path('tenants').$name))
		{
			echo "ERROR! This tenant does not exist!";
			return false;
		}	

		$this->message("Updating database user... ");
		if (!$this->cp->update_database_user($name, $new_pass))
		{
			echo "ERROR! Could not update the database user!";
			return false;
		}
		$this->message('ok!', true);

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
			echo "Usage: php artisan tenant:remove [name]";
			return false;
		}

		$name = $args[0];

		$this->message("Removing tenant directory... ");
		if (!$this->delete_tenant_folder($name))
		{
			echo "ERROR! This tenant does not exist!";
			return false;
		}
		$this->message('ok!', true);

		$this->message("Removing database and user... ");
		if (!$this->cp->remove_database_and_user($name, $name))
		{
			echo "ERROR! Could not remove database!";
			return false;
		}
		$this->message('ok!', true);

		// TODO: remove subdomain (with associated directory)

		echo "DONE! Tenant ($name) is removed from the system.";
		return true;
	}

	private function create_tenant_folder($name)
	{
		if (!file_exists(path('tenants').$name))
		{
			return $this->recursive_copy(path('tenants').'default', path('tenants').$name);
		}
		else
		{
			return false;
		}
	}

	private function recursive_copy($src, $dst)
	{
	    $dir = opendir($src); 
	    mkdir($dst); 

	    $retval = true;

	    while($retval && false !== ( $file = readdir($dir)) )
	    { 
	        if (( $file != '.' ) && ( $file != '..' ))
	        { 
	            if ( is_dir($src . '/' . $file) )
	            { 
	                $this->recursive_copy($src . '/' . $file,$dst . '/' . $file); 
	            } 
	            else 
	            { 
	                $retval = copy($src . '/' . $file,$dst . '/' . $file); 
	            } 
	        } 
	    }

	    closedir($dir); 

	    return $retval;
	}

	private function delete_tenant_folder($name)
	{
		if (file_exists(path('tenants').$name))
		{
			$it = new RecursiveDirectoryIterator(path('tenants').$name);
			$files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
			
			foreach($files as $file)
			{
			    if ($file->isDir())
			    {
			        @rmdir($file->getRealPath());
			    } 
			    else
			    {
			        unlink($file->getRealPath());
			    }
			}

			return rmdir(path('tenants').$name);
		}
		else
		{
			return false;
		}
	}

	private function message($msg, $newline = false)
	{
		echo $msg.($newline ? "\n" : "");
		flush();
		ob_flush();
	}
}