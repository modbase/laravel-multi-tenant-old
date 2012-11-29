# Laravel Multitenancy

[![Build Status](https://secure.travis-ci.org/modbase/laravel-multi-tenant.png)](http://travis-ci.org/modbase/laravel-multi-tenant)

## Introduction

This is a fork of the Laravel PHP framework, which now supports multi-tenancy. The folder structure has changed a bit to be able to provide this feature. You'll notice that the `public` and `storage` directories are gone. We now use the `tenants` directory. This folder will contain all the tenant-specific data (including the well-known `public` and `storage` directories). The `default` folder is a skeleton for the other tenant folders and **must not** be removed!

By using this spin-off, you will be able to create **multiple sites** powered by the **same codebase**. This might even be a stepping stone for a Laravel featured **SaaS product**.

## Setup and configuration

### System

The system has its own small configuration, which can be found in `bundles/tenancy/config/options.php`:

```
return array(
    'db_prefix' => 'lv_',       // Database prefix for tenants, i.e. tenant database name & username will be {db_prefix}{tenant_name}. Leave empty if not required.
);
```

The comments should be pretty much self explanatory.

**Important note:** be careful with changing the `db_prefix` when you already have added tenants to the system. You will be unable to delete the database of those when using the incorrect prefix!

### Tenants

Each tenant must have its own directory in the `tenants` folder. The easiest way is to copy the complete `default` folder and rename it to e.g. `site1`. Inside you will find two other folders: `public` and `storage`. The latter will contain all the session information, cached views etc. just like in the original Laravel. The `public` directory contains your public data, such as CSS files, images etc. This whole process can be **automated** by using the artisan task (see below).

When you create a new tenant, we suggest you to create a new VirtualHost and point it to the respective public directory. For example:


```
<VirtualHost 127.0.0.2:80>
    DocumentRoot "/path/to/laravel/tenants/site1/public"
    ServerName "site1.dev"
</VirtualHost>
```

The `config.php` file of the tenant contains the database settings for this specific tenant:

```
define('DB_NAME', 'lv_tenantname');
define('DB_USER', 'lv_tenantname');
define('DB_PASS', 'tenant_password_here');
```

Each tenant should have it's own database. Add the database information corresponding to the tenant in this file. Again, this is **not necessary** when using the artisan task.


## Artisan task

We can speed up the above process with a factor of... a lot! Now you can use the great `artisan` commandline tool.

We have provided five commands (feel free to add your own):

* `php artisan tenancy::manage:show`

Lists all available tenants.

* `php artisan tenancy::manage:add <name> [<pass>]`

Adds a new tenant to the system with the provided name and the (optional) provided database password. If no password is provided, a random one will be generated.

* `php artisan tenancy::manage:remove <name> [<second_name> ...]`

Removes the given tenant(s), thus whole tenant folder and database.

* `php artisan tenancy::manage:update <name> <pass>`

Sets a new password for the database of the given tenant.

* `php artisan tenancy::manage:reset <name>`

Same as above, but will generate a random password for you.

## Improvements

This is just an early release. There are a lot of improvements to do. Currently, only MySQL has been _implemented_.

## Contribute

Found a bug, got another awesome change? Just send us a pull request! The community thanks you :)

## License

Copyright 2012 Stijn Geselle

Licensed under the MIT License