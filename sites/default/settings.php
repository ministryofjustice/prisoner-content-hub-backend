<?php

$databases = [];
$databases['default']['default'] = array(
  'database' => getenv('HUB_DB_ENV_MYSQL_DATABASE', true),
  'username' => getenv('HUB_DB_ENV_MYSQL_USER', true),
  'password' => getenv('HUB_DB_ENV_MYSQL_PASSWORD', true),
  'prefix' => '',
  'host' => getenv('HUB_DB_PORT_3306_TCP_ADDR', true),
  'port' => getenv('HUB_DB_PORT_3306_TCP_PORT', true),
  'namespace' => 'Drupal\\Core\\Database\\Driver\\mysql',
  'driver' => 'mysql',
);

$settings['install_profile'] = 'standard';
$settings['hash_salt'] = 'TDVdRVDjXzm2ASUFPQ2rVUys-wiXvnYar9n2CWrQXefT1Hc3pLOhDC0lPtgLQcfoPViNEwWo3g';
$settings['update_free_access'] = FALSE;
$settings['container_yamls'][] = $app_root . '/' . $site_path . '/services.yml';
$settings['file_scan_ignore_directories'] = [
  'node_modules',
  'bower_components',
];
$settings['entity_update_batch_size'] = 50;
$settings['entity_update_backup'] = TRUE;
$settings['file_public_base_url'] = getenv('HUB_EXT_FILE_URL', true);

$config_directories['sync'] = 'sites/default/files/config/sync';

$config_directories = [
  CONFIG_SYNC_DIRECTORY => 'sites/default/files/config/sync'
];

if (file_exists($app_root . '/' . $site_path . '/settings.local.php')) {
  include $app_root . '/' . $site_path . '/settings.local.php';
}
