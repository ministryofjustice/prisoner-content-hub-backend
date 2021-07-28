<?php

$databases = [];
$databases['default']['default'] = array(
  'database' => getenv('HUB_DB_ENV_MYSQL_DATABASE', true),
  'username' => getenv('HUB_DB_ENV_MYSQL_USER', true),
  'password' => getenv('HUB_DB_ENV_MYSQL_PASSWORD', true),
  'prefix' => '',
  'host' => getenv('HUB_DB_PORT_3306_TCP_ADDR', true),
  'port' => '3306',
  'namespace' => 'Drupal\\Core\\Database\\Driver\\mysql',
  'driver' => 'mysql',
);

$trusted_hosts = getenv('TRUSTED_HOSTS', true);

$settings['trusted_host_patterns'] = [
  $trusted_hosts
];

/**
 * Flysystem S3 filesystem configuration
 */
$flysystem_schemes = [

  'local-files' => [
    'driver' => 'local',
    'config' => [
      'root' => 'sites/default/files',
      'public' => TRUE
    ],
    'description' => 'Local. This is a Flysystem reference to the Drupal "files" directory.',

    // We don't need to cache local files
    'cache' => FALSE
  ],

  's3' => [
    'driver' => 's3',
    'config' => [
      'key'    => getenv('FLYSYSTEM_S3_KEY', true),
      'secret' => getenv('FLYSYSTEM_S3_SECRET', true),
      'region' => getenv('FLYSYSTEM_S3_REGION', true),
      'bucket' => getenv('FLYSYSTEM_S3_BUCKET', true),

      // Optional configuration settings.

      'options' => [
        'ACL' => 'private',
      ],

      // Autodetected based on the current request if not provided
      'protocol' => 'https',

      // Directory prefix for all viewed files
      // 'prefix' => 'an/optional/prefix',

      // A CNAME that resolves to your bucket. Used for URL generation
      'cname' => getenv('FLYSYSTEM_S3_CNAME', true),

      // Set to FALSE if the CNAME does not resolve to a bucket and the bucket
      // should be included in the path.
      'cname_is_bucket' => getenv('FLYSYSTEM_S3_CNAME_IS_BUCKET', true),

      // Set to TRUE to link to files using direct links
      'public' => TRUE,

      'expires' => '+3600 seconds',

      // Set to TRUE if CORS upload support is enabled for the bucket
       'cors' => TRUE,
    ],

    'cache' => TRUE, // Creates a metadata cache to speed up lookups
  ],
];

$settings['flysystem'] = $flysystem_schemes;

// Prevent file permission errors when image styles are generated.
// @See https://www.drupal.org/project/flysystem_s3/issues/3058063#comment-14164774
$settings['file_chmod_directory'] = 0700;
$settings['file_chmod_file'] = 0600;

// Remove the ?itok parameter from image style urls, these interfere with the
// aws signature.  The DDOS protection that the itok parameter brings is not
// required (as the assets are hosted on s3).
// See https://www.drupal.org/project/flysystem_s3/issues/2772847#comment-14156598
$config['image.settings']['suppress_itok_output'] = TRUE;

$settings['hash_salt'] = getenv('HASH_SALT', true);
$settings['update_free_access'] = FALSE;
$settings['container_yamls'][] = $app_root . '/' . $site_path . '/services.yml';
$settings['file_scan_ignore_directories'] = [
  'node_modules',
  'bower_components',
];
$settings['entity_update_batch_size'] = 50;
$settings['entity_update_backup'] = TRUE;
$settings['file_public_base_url'] = getenv('FILE_PUBLIC_BASE_URL', true);
$elasticsearch_cluster = getenv("ELASTICSEARCH_CLUSTER", true);
$config['elasticsearch_connector.cluster.'.$elasticsearch_cluster]['url'] = getenv("ELASTICSEARCH_HOST", true);

// Configuration options can be found here: https://git.drupalcode.org/project/raven/-/blob/8.x-2.x/config/install/raven.settings.yml
//
// We don't _need_ to specify the DSN, environment, or release here, but doing so
// displays the setting in the UI, making debugging easier
$config['raven.settings'] = [
  'client_key' => getenv("SENTRY_DSN", true),
  'environment' => getenv("SENTRY_ENVIRONMENT", true),
  'release' => getenv("SENTRY_RELEASE", true),
  'log_levels' => [
    1, // Emergency
    2, // Alert
    3, // Critical
    4, // Error
    // 5, // Warning
    // 6, // Notice
    // 7, // Info
    // 8  // Debug
  ],
  'fatal_error_handler' => true
];

$settings['config_sync_directory'] = '../config/sync';

if (file_exists($app_root . '/' . $site_path . '/settings.local.php')) {
  include $app_root . '/' . $site_path . '/settings.local.php';
}

/**
 * Reverse proxy settings.
 *
 * For more information, see default.settings.php
 */
if (PHP_SAPI !== 'cli') {
  // Tell Drupal we are running behind a reverse proxy.
  // This allows the real client IP to be used for logging.
  $settings['reverse_proxy'] = TRUE;

  // Drupal asks for the IP addresses that the proxy requests will come in from,
  // for additional security, to prevent IP spoofing.
  // As these addresses can change, we will just dynamically set the value.
  $settings['reverse_proxy_addresses'] = [$_SERVER['REMOTE_ADDR']];

  // Setting the trusted reverse proxy header, to further prevent IP spoofing.
  $settings['reverse_proxy_trusted_headers'] = \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_ALL;
}
