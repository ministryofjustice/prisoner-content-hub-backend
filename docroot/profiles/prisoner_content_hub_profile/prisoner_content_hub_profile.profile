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


/**
 * Implements hook_mail_alter().
 *
 * Disable all email messages from being sent, as they just result in an error
 * in the logs.
 */
function prisoner_content_hub_profile_mail_alter(&$message) {
  $message['send'] = FALSE;
}

/**
 * Implements hook_file_validate().
 *
 * Prevent 0 byte files from being uploaded.
 *
 * We have previously seen invalid video files being uploaded, that are 0 bytes.
 * These pass validation, so the issue is not spotted until someone tries to
 * play the video on the frontend.
 */
function prisoner_content_hub_profile_file_validate(\Drupal\file\FileInterface $file) {
  $errors = [];
  if (!$file->getSize()) {
    $errors[] = t("The file is invalid.  Please check the file and try again.");
  }
  return $errors;
}
