<?php

use Drupal\Core\Installer\InstallerKernel;

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

$settings['trusted_host_patterns'] = [
  getenv('TRUSTED_HOSTS', true),
  getenv('TRUSTED_HOSTS_JSONAPI', true),
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

      // Directory prefix for all viewed files
      // 'prefix' => 'an/optional/prefix',

      // A CNAME that resolves to your bucket. Used for URL generation
      'cname' => getenv('FLYSYSTEM_S3_CNAME', true),

      // Since env variables are strings, we must check the value for "true" otherwise
      // assume FALSE.
      'cname_is_bucket' => getenv('FLYSYSTEM_S3_CNAME_IS_BUCKET', TRUE) === "true",

      // Set to TRUE to link to files using direct links
      'public' => TRUE,

      'expires' => strtotime('tomorrow +3 hours', $_SERVER['REQUEST_TIME']),

      // Set to TRUE if CORS upload support is enabled for the bucket
      'cors' => TRUE,

      // Optionally specify an alternative endpoint.  Used for localstack.
      // If not set the default AWS endpoint is used.
      // This must be set to NULL if not being used.
      'endpoint' =>  getenv('FLYSYSTEM_S3_ENDPOINT', TRUE) ? getenv('FLYSYSTEM_S3_ENDPOINT', TRUE) : NULL,

      // Optionally set to path style endpoint.  Used for localstack.
      'use_path_style_endpoint' => getenv('FLYSYSTEM_S3_USE_PATH_STYLE_ENDPOINT', TRUE) === "true",
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

// Raven (sentry integration) module allows for setting values via environment
// variables.  See https://git.drupalcode.org/project/raven/-/blob/14ddb8158b480c2e65884b4d4c561a14c17acf2b/README.md#L109
// We also set them here so that they show up on the admin/config/development/logging
// to avoid any confusion.
$config['raven.settings'] = [
  'client_key' => getenv("SENTRY_DSN", true),
  'environment' => getenv("SENTRY_ENVIRONMENT", true),
  'release' => getenv("SENTRY_RELEASE", true),
];
// We want to ignore all flysytem errors on non-prod environments.
// There will be lots of these due to missing files.
if ($config['raven.settings']['environment'] != 'production') {
  $config['raven.settings']['ignored_channels'] = ['flysystem'];
}

// Do not load Redis during installation (required for CircleCI builds).
// See https://www.drupal.org/project/redis/issues/2876132#comment-13054928
if (!InstallerKernel::installationAttempted() && extension_loaded('redis')) {
  $settings['redis.connection']['interface'] = 'PhpRedis';
  if (getenv('REDIS_TLS_ENABLED', 'true') == 'true') {
    $settings['redis.connection']['host'] = 'tls://' . getenv('REDIS_HOST', true);
  }
  else {
    $settings['redis.connection']['host'] = getenv('REDIS_HOST', true);
  }
  if (getenv('REDIS_PASSWORD', true)) {
    $settings['redis.connection']['password'] = getenv('REDIS_PASSWORD', true);
  }
  $settings['cache']['default'] = 'cache.backend.redis';

  // Load in the services config directly from the module.  This allows
  // for any updates to be automatically added, and also ensures we do not add
  // the config during site installation (which will result in an error).
  $settings['container_yamls'][] = 'modules/contrib/redis/example.services.yml';

  // Set a prefix for Redis cache entries.  Otherwise the Redis module will
  // generate one, via Settings::getApcuPrefix().  This can change over time
  // (e.g. when updating Drupal versions) resulting in lots stale cache items
  // in the cache.
  $settings['cache_prefix'] = 'prisoner_content_hub_backend';

}

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
  $settings['reverse_proxy_trusted_headers'] = \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_FOR | \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_HOST | \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_PORT | \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_PROTO | \Symfony\Component\HttpFoundation\Request::HEADER_FORWARDED;
}
