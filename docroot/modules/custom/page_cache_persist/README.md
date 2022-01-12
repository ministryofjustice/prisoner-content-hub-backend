# Page Cache Persist

### What
This module makes Drupal's internal page cache persist when Drupal tries to clear all caches.
E.g. when running `drush cache:rebuild`.  By default, the page cache would also be cleared,
but with this module enabled, the page cache contents will be retained.

This prevents performance issues on production sites, particularly wth deployments.

Note that if an individual page is cleared, this will still work as normal (i.e. be deleted).
This module only prevents clearing of the entire page cache, not specific items.

### Why?
Drupal's internal page cache module is often used for smaller sites.  It's assumed that larger,
higher-traffic websites, will use an external cache such as Varnish or a CDN.

Drupal regularly clears out the entire cache, particularly on deployments.
E.g. `drush cache-rebuild` is part of the `drush deploy` command. See https://www.drush.org/latest/deploycommand/

When using an external cache, it is unlikely you would clear the entire cache on deployments.

Note that if you still want to clear the entire page cache, there is a drush command provided by
this module for doing so:
```
drush cache:force-clear-page
```
