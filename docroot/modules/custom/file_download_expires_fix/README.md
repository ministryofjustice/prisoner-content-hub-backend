# File Download Expires Fix

This is a small module that fixes an issue where Symfony's BinaryFileResponse sets the following
header on all cacheable file responses.
```
Cache-Control: public
```

Whilst there is nothing wrong with this.  It means that Drupal's default mod_expires functionality
(found in .htaccess) will not work.  As mod_expires only works when there are no cache headers
already set on the response.
This module simply removes these headers, allowing mod_expires to do it's magic.

It's assumed that mod_expires is enabled, and the standard htaccess from Drupal core is being used.

@see https://www.drupal.org/project/drupal/issues/3263593
