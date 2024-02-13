<?php

/**
 * @file
 * Site settings file.
 */

use Drupal\Core\Installer\InstallerKernel;
use Symfony\Component\HttpFoundation\Request;

$databases = [];
$databases['default']['default'] = [
  'database' => getenv('HUB_DB_ENV_MYSQL_DATABASE', TRUE),
  'username' => getenv('HUB_DB_ENV_MYSQL_USER', TRUE),
  'password' => getenv('HUB_DB_ENV_MYSQL_PASSWORD', TRUE),
  'prefix' => '',
  'host' => getenv('HUB_DB_PORT_3306_TCP_ADDR', TRUE),
  'port' => '3306',
  'namespace' => 'Drupal\\Core\\Database\\Driver\\mysql',
  'driver' => 'mysql',
];

$settings['trusted_host_patterns'] = [
  getenv('TRUSTED_HOSTS', TRUE),
  getenv('TRUSTED_HOSTS_JSONAPI', TRUE),
];

/**
 * Flysystem S3 filesystem configuration.
 */
$settings['flysystem'] = [

  'local-files' => [
    'driver' => 'local',
    'config' => [
      'root' => 'sites/default/files',
      'public' => TRUE,
    ],
    'description' => 'Local. This is a Flysystem reference to the Drupal "files" directory.',

    // We don't need to cache local files.
    'cache' => FALSE,
  ],

  's3' => [
    'driver' => 's3',
    'config' => [
      'key'    => getenv('FLYSYSTEM_S3_KEY', TRUE),
      'secret' => getenv('FLYSYSTEM_S3_SECRET', TRUE),
      'region' => getenv('FLYSYSTEM_S3_REGION', TRUE),
      'bucket' => getenv('FLYSYSTEM_S3_BUCKET', TRUE),

      // Directory prefix for all viewed files
      // 'prefix' => 'an/optional/prefix',.
      // A CNAME that resolves to your bucket. Used for URL generation.
      'cname' => getenv('FLYSYSTEM_S3_CNAME', TRUE),

      // Since env variables are strings, we must check the value for "true",
      // otherwise assume FALSE.
      'cname_is_bucket' => getenv('FLYSYSTEM_S3_CNAME_IS_BUCKET', TRUE) === "true",

      // Set to TRUE to link to files using direct links.
      'public' => TRUE,

      'expires' => strtotime('tomorrow +3 hours', $_SERVER['REQUEST_TIME']),

      // Set to TRUE if CORS upload support is enabled for the bucket.
      'cors' => TRUE,

      // Optionally specify an alternative endpoint.  Used for localstack.
      // If not set the default AWS endpoint is used.
      // This must be set to NULL if not being used.
      'endpoint' => getenv('FLYSYSTEM_S3_ENDPOINT', TRUE) ? getenv('FLYSYSTEM_S3_ENDPOINT', TRUE) : NULL,

      // Optionally set to path style endpoint.  Used for localstack.
      'use_path_style_endpoint' => getenv('FLYSYSTEM_S3_USE_PATH_STYLE_ENDPOINT', TRUE) === "true",
    ],
    // Creates a metadata cache to speed up lookups.
    'cache' => TRUE,
  ],
];

// Copy over our S3 config and set up a new scheme that is used just for css/js.
$settings['flysystem']['s3-css-js'] = $settings['flysystem']['s3'];
$settings['flysystem']['s3-css-js']['serve_js'] = TRUE;
$settings['flysystem']['s3-css-js']['serve_css'] = TRUE;

// Set public to FALSE, so that files are downloaded through Drupal
// (and not directly from S3).
// This prevents us having to deal with S3 signatures.  Allowing css/js to be
// cached in the user's browser.
// Also, serving JS from the same origin fixes an issue when opening dialog
// windows in ckeditor (e.g. image upload). See https://trello.com/c/48w0up7I
$settings['flysystem']['s3-css-js']['config']['public'] = FALSE;

// Remove the ?itok parameter from image style urls, these interfere with the
// aws signature.
// The itok param is related to DDoS protection: SA-CORE-2013-002
// However the protection itself is actually handled by a different setting:
// `allow_insecure_derivatives` which we leave as FALSE.
// I.e. there is no vulnerability in using `suppress_itok_output = TRUE`.
// @see \Drupal\image\Entity\ImageStyle::buildUrl().
// @see \Drupal\image\Controller\ImageStyleDownloadController::deliver().
$config['image.settings']['suppress_itok_output'] = TRUE;

$settings['hash_salt'] = getenv('HASH_SALT', TRUE);
$settings['update_free_access'] = FALSE;
$settings['container_yamls'][] = $app_root . '/' . $site_path . '/services.yml';
$settings['file_scan_ignore_directories'] = [
  'node_modules',
  'bower_components',
];
$settings['entity_update_batch_size'] = 50;
$settings['entity_update_backup'] = TRUE;
$settings['file_public_base_url'] = getenv('FILE_PUBLIC_BASE_URL', TRUE);
$config['elasticsearch_connector.cluster.opensearch']['url'] = getenv('OPENSEARCH_HOST', TRUE);

// Raven (sentry integration) module allows for setting values via environment
// variables.  See https://git.drupalcode.org/project/raven/-/blob/14ddb8158b480c2e65884b4d4c561a14c17acf2b/README.md#L109
// We also set them here so that they show up on the
// admin/config/development/logging to avoid any confusion.
$config['raven.settings'] = [
  'client_key' => getenv("SENTRY_DSN", TRUE),
  'environment' => getenv("SENTRY_ENVIRONMENT", TRUE),
  'release' => getenv("SENTRY_RELEASE", TRUE),
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
    $settings['redis.connection']['host'] = 'tls://' . getenv('REDIS_HOST', TRUE);
  }
  else {
    $settings['redis.connection']['host'] = getenv('REDIS_HOST', TRUE);
  }
  if (getenv('REDIS_PASSWORD', TRUE)) {
    $settings['redis.connection']['password'] = getenv('REDIS_PASSWORD', TRUE);
  }
  $settings['cache']['default'] = 'cache.backend.redis';

  // Load in the services config directly from the module.  This allows
  // for any updates to be automatically added, and also ensures we do not add
  // the config during site installation (which will result in an error).
  $settings['container_yamls'][] = 'modules/contrib/redis/example.services.yml';

  // Set a prefix for Redis cache entries. Otherwise, the Redis module will
  // generate one, via Settings::getApcuPrefix().  This can change over time
  // (e.g. when updating Drupal versions) resulting in lots stale cache items
  // in the cache.
  $settings['cache_prefix'] = 'prisoner_content_hub_backend';

}

// Set API key for govuk_notify for sending emails.
$config['govuk_notify.settings']['api_key'] = getenv('GOVUK_NOTIFY_API_KEY', TRUE);

// Set GA ID.
$config['google_analytics.settings']['account'] = getenv('ANALYTICS_SITE_ID', TRUE);

$settings['config_sync_directory'] = '../config/sync';

if (file_exists($app_root . '/' . $site_path . '/settings.local.php')) {
  include $app_root . '/' . $site_path . '/settings.local.php';
}

/**
 * Reverse proxy settings.
 *
 * For more information, see default.settings.php.
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
  $settings['reverse_proxy_trusted_headers'] = Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_FORWARDED;
}

// Set max_execution_time to 30 seconds, as this is the same timeout as on the
// frontend.  Note this does not apply to cli commands.
if (PHP_SAPI !== 'cli') {
  ini_set('max_execution_time', 30);
}
