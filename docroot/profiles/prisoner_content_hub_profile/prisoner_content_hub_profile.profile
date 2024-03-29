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

use Drupal\dynamic_entity_reference\Plugin\Field\FieldWidget\DynamicEntityReferenceWidget;
use Drupal\taxonomy\Entity\Vocabulary;

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

/**
 * Implements hook_field_widget_form_alter().
 *
 * For dynamic_entity_reference fields, change the select option "Taxonomy term"
 * to be a comma separated list of Taxonomy vocabularies.
 */
function prisoner_content_hub_profile_field_widget_form_alter(&$element, \Drupal\Core\Form\FormStateInterface $form_state, $context) {
  if ($context['widget'] instanceof DynamicEntityReferenceWidget) {
    if (isset($element['target_type']['#options']['taxonomy_term'])) {
      $target_bundle_labels = [];
      foreach ($context['items']->getFieldDefinition()->getSetting('taxonomy_term')['handler_settings']['target_bundles'] as $bundle) {
        if ($bundle == 'moj_categories') {
          // A bit of custom tweaking here.
          // The Vocabulary is called "Categories" but we only ever allow users
          // to select child categories.  So we instead refer to it as
          // "Subcategories".
          $target_bundle_labels[] = 'Subcategories';
        }
        else {
          $vocab = Vocabulary::load($bundle);
          $target_bundle_labels[] = $vocab->label();
        }
      }
      $last = array_pop($target_bundle_labels);
      $string = implode(', ', $target_bundle_labels);
      if ($string) {
        $string .= ' or ';
      }
      $string .= $last;
      $element['target_type']['#options']['taxonomy_term'] = $string;
    }
  }
}

/**
 * Implements hook_form_FORM_ID_alter().
 *
 * Alter the urgent_banner form to add a warning message about changes taking
 * upto 5 minutes due to caching.
 */
function prisoner_content_hub_profile_form_node_form_alter(&$form, \Drupal\Core\Form\FormStateInterface $form_state, $form_id) {
  /** @var \Drupal\node\NodeInterface $node */
  $node = $form_state->getFormObject()->getEntity();
  if ($node->getType() == 'urgent_banner') {
    // Add submit handler to form.  Note this has to be added to the "actions".
    // @See https://drupal.stackexchange.com/a/223355
    foreach (array_keys($form['actions']) as $action) {
      if ($action != 'preview' && isset($form['actions'][$action]['#type']) && $form['actions'][$action]['#type'] === 'submit') {
        $form['actions'][$action]['#submit'][] = 'prisoner_content_hub_profile_form_node_form_submit';
      }
    }
  }
}

/**
 * Submit handler for the urgent_banenr node edit form.
 *
 * Add the warning message to the user.
 */
function prisoner_content_hub_profile_form_node_form_submit($form, $form_state) {
  $node = $form_state->getFormObject()->getEntity();
  \Drupal::messenger()->addWarning('It may take upto 5 minutes for your changes to appear on the hub.');
}
