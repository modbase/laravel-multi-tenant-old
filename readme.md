# Laravel Multitenancy

## Introduction

This is a fork of the Laravel PHP framework, which now supports multi-tenancy. The folder structure has changed a bit to be able to provide this feature. You'll notice that the `public` and `storage` directories are gone. We now use the `tenants` directory. This folder will contain all the tenant-specific data (including the well-known `public` and `storage` directories).

By using this spin-off, you will be able to create **multiple sites** powered by the **same codebase**. This might even be a stepping stone for a Laravel featured **SaaS product**.

## Setup and configuration

Each tenant must have it's own directory in the `tenants` folder. The easiest way is to copy the complete `default` folder and rename it to e.g. `site2`. Inside you will find two other folders: `public` and `storage`. The latter will contain all the session information, cached views etc. just like in the original Laravel. The `public` directory contains your public data, such as CSS files, images etc. When you create a new tenant, we suggest you to create a new VirtualHost and point it to the respective public directory. For example:


```
<VirtualHost 127.0.0.2:80>
    DocumentRoot "/path/to/laravel/tenants/default/public"
    ServerName "default.dev"
</VirtualHost>

<VirtualHost 127.0.0.3:80>
    DocumentRoot "/path/to/laravel/tenants/site1/public"
    ServerName "site1.dev"
</VirtualHost>

```

The `config.php` file contains the database settings:

```
define('DB_NAME', 'lv_test1');
define('DB_USER', 'root');
define('DB_PASS', '');
```

Each tenant should have it's own database. Add the database information corresponding to the tenant in this file.


## Artisan command

When you have cPanel, you can speed the above process up with a factor of ... a lot! Now you can use the great `artisan` command.

We have provided four possible commands:

* `php artisan tenancy::manage:add [name] [pass]` - This will add a new tenant to the system with the provided name and the provided database password.
* `php artisan tenancy::manage:remove [name]` - This command will remove the given tenant (all files and database!).
* `php artisan tenancy::manage:update [name] [pass]` - This enables you to set a new password for the database.
* `php artisan tenancy::manage:reset [name]` - This is the same as above, but will generate a random password for you.

## Improvements

This is just an early release. There are a lot of improvements to do. Currently, only MySQL has been _implemented_. Also, the cPanel library should be expanded to contain all the API functions.

## Contribute

Found a bug, got another awesome change? Just send us a pull request! The community thanks you :)