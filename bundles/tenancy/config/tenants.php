<?php

/*
|--------------------------------------------------------------------------
| Warning: Do not change
|--------------------------------------------------------------------------
|
| It is important that you do not change this file manually. Unless you
| know what you are doing. This file is read via a regex algorithm so if
| there is a space in the wrong place, tenants may not be accessable.
|
*/

return 
#BEGIN_TENANTS_LIST
array (
  'default' => 
  array (
    'driver' => 'mysql',
    'host' => 'localhost',
    'database' => 'tenant_default',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8',
    'prefix' => '',
  ),
);
#END_TENANTS_LIST