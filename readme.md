# Laravel Multitenancy

## Introduction

This is a fork of the Laravel PHP framework, which now supports multi-tenancy. The folder structure has changed a bit to be able to provide this feature. You'll notice that the `public` and `storage` directories are gone. We now use the `tenants` directory. This folder will contain all the tenant-specific data (including the well-known `public` and `storage` directories).

By using this spin-off, you will be able to create **multiple sites** powered by the **same codebase**. This might even be a stepping stone for a Laravel featured **SaaS product**.

## Setup and configuration

### System

The system has its own small configuration, which can be found in `bundles/tenancy/config/options.php`:

```
return array(
    'enable_cpanel' => false,           // Enable/disable cPanel features
    'cpanel_user' => 'your_cpanel',     // Your cPanel username
    'cpanel_pass' => 'your_pass',       // Your cPanel password
    'cpanel_host' => 'your_hostname',   // The hostname (or IP) where cPanel is located
    'cpanel_port' => 2083,              // This port should be ok by default
    'debug' => false,                   // Display debug messages of the Cpanel class
);
```

The comments should be pretty much self explanatory.

### Tenants

Each tenant must have its own directory in the `tenants` folder. The easiest way is to copy the complete `default` folder and rename it to e.g. `site2`. Inside you will find two other folders: `public` and `storage`. The latter will contain all the session information, cached views etc. just like in the original Laravel. The `public` directory contains your public data, such as CSS files, images etc. This whole process can be **automated** by using the artisan task (see below).

When you create a new tenant, we suggest you to create a new VirtualHost and point it to the respective public directory. For example:


```
<VirtualHost 127.0.0.1:80>
    DocumentRoot "/path/to/laravel/tenants/default/public"
    ServerName "default.dev"
</VirtualHost>

<VirtualHost 127.0.0.2:80>
    DocumentRoot "/path/to/laravel/tenants/site1/public"
    ServerName "site1.dev"
</VirtualHost>
```

The `config.php` file of the tenant contains the database settings:

```
define('DB_NAME', 'lv_default');
define('DB_USER', 'root');
define('DB_PASS', '');
```

Each tenant should have it's own database. Add the database information corresponding to the tenant in this file. Again, this is **not necessary** when using the artisan task.


## Artisan task

We can speed up the above process with a factor of... a lot! Now you can use the great `artisan` command.

We have provided five commands (feel free to add your own):

* `php artisan tenancy::manage:list_all` - This will add a new tenant to the system with the provided name and the provided database password.
* `php artisan tenancy::manage:add [name] [pass]` - Add a new tenant to the system with the provided name and the (optional) provided database password. If no password is provided, a random one will be generated.
* `php artisan tenancy::manage:remove [name]` - Remove the given tenant (all files and database!).
* `php artisan tenancy::manage:update [name] [pass]` - Set a new password for the database.
* `php artisan tenancy::manage:reset [name]` - Same as above, but will generate a random password for you.

## Improvements

This is just an early release. There are a lot of improvements to do. Currently, only MySQL has been _implemented_. Also, the cPanel library should be expanded to contain all the API functions.

## Contribute

Found a bug, got another awesome change? Just send us a pull request! The community thanks you :)