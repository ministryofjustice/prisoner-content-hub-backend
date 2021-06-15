<?php

/**
 * @file
 * Primary hook implementations for Prisoner content hub profile.
 *
 * Hooks in this profile _mostly_ function in a similar way to a module (there
 * are some small differences).
 * Using a profile is often a nice place to put things that
 * don't belong to a particular module, and are global to the site.
 */


/**
 * Implements hook_toolbar_alter().
 */
function prisoner_content_hub_profile_toolbar_alter(&$items) {
  // Remove the "Back to site" button from toolbar (as we're not using Drupal
  // for the front end).
  // TODO: We could possibly set the link here to be the front end site for the
  // prison that the user is associated with (if we were to start assigning
  // users to prisons).
  unset($items['home']);
}
