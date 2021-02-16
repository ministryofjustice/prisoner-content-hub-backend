# Prisoner Content Hub Profile

A Drupal installation profile for the prisoner content hub.

The initial purpose of using this profile, is to allow us to install Drupal
with our own configuration.
See https://www.drupal.org/node/2897299

Previously we were using the standard (core) profile.  However this is not
(currently) compatible with the `--existing-config` option, as it implements
a `hook_install()`.  Therefore we have switched to our own custom profile
that does not implement the hook.

### Why install with existing config?

When running tests, we want to do so on a freshly installed version of Drupal
(so that we do not have to worry about database importing).
We can do so by running something like this:
```
drush site-install --existing-config
```
Using existing config is hugely beneficial, as it means there is only one
configuration import.  Installing Drupal _without_ this option means you are
importing config twice (once on the installation, and a second time to import
your own config). The config-import process is prone to errors, and supporting
an additional import process is unnecessary and time-consuming.

### Other uses of this profile
One further benefit is being able to use the profiles install file to implement
database updates (via `hook_update_n`).
Other than that, there isn't a huge amount of functionality that an installation
profile can provide us.  Other than being able to control the installation process.
