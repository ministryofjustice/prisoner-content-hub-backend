<?php

/**
 * This is a NAME.deploy.php file. It contains "deploy" functions. These are
 * one-time functions that run *after* config is imported during a deployment.
 * These are a higher level alternative to hook_update_n and
 * hook_post_update_NAME functions. See
 * https://www.drush.org/latest/deploycommand/#authoring-update-functions for a
 * detailed comparison.
 */

use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

/**
 * Bulk update published content with new prison field.
 */
function prisoner_content_hub_profile_deploy_update_content_to_new_prison_field(&$sandbox) {
  if (!isset($sandbox['progress'])) {
    $sandbox['progress'] = 0;
    $nodes_query = \Drupal::entityQuery('node')->exists('field_moj_prisons')->accessCheck(FALSE);
    $sandbox['result_nodes'] = $nodes_query->execute();
    $terms_query = \Drupal::entityQuery('taxonomy_term')->exists('field_moj_prisons')->accessCheck(FALSE);
    $sandbox['result_terms'] = $terms_query->execute();
    prisoner_content_hub_profile_create_new_prison_categories($sandbox);
  }

  if ($sandbox['progress'] < count($sandbox['result_nodes'])) {
    $entities = Node::loadMultiple(array_slice($sandbox['result_nodes'], $sandbox['progress'], 50, TRUE));
  }
  else {
    $entities = Term::loadMultiple(array_slice($sandbox['result_terms'], $sandbox['progress'] - count($sandbox['result_nodes']), 50, TRUE));
  }

  /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
  foreach ($entities as $entity) {
    $prison_category_values = array_column($entity->get('field_prison_categories')->getValue(), 'target_id');
    $new_prison_categories_value = [];
    foreach ($prison_category_values as $prison_category_value) {
      $new_prison_categories_value[] = ['target_id' => $sandbox['prison_categories_map'][$prison_category_value]];
    }
    $prisons = $entity->get('field_moj_prisons')->referencedEntities();
    $new_prisons_value = [];
    /** @var \Drupal\taxonomy\TermInterface $prison */
    foreach ($prisons as $prison) {
      // Only add prisons that are in _other_ categories.
      if (!in_array($prison->get('parent')->target_id, array_column($new_prison_categories_value, 'target_id'))) {
        $new_prisons_value[] = ['target_id' => $prison->id()];
      }
    }
    $entity->set('field_prisons', array_merge($new_prison_categories_value, $new_prisons_value));
    $entity->save();
    $sandbox['progress']++;
  }

  $sandbox['#finished'] = $sandbox['progress'] >= count($sandbox['result_nodes']) + count($sandbox['result_terms']);
  if ($sandbox['#finished'] ) {
    \Drupal::service('module_installer')->install(['prisoner_hub_prison_access']);
    \Drupal::service('module_installer')->uninstall(['prisoner_hub_entity_access']);
    return 'Completed updated, processed total of: ' . $sandbox['progress'];
  }
  return 'Processed entities: ' . $sandbox['progress'];
}

/**
 * Create new prison category terms, within the current prisons taxonomy.
 *
 * Note this is *not* a deploy hook.
 */
function prisoner_content_hub_profile_create_new_prison_categories(&$sandbox) {
  $female_term = Term::create(['name' => 'Female', 'vid' => 'prisons']);
  $adult_male_term = Term::create(['name' => 'Adult male', 'vid' => 'prisons']);
  $youth_male_term = Term::create(['name' => 'Youth male', 'vid' => 'prisons']);
  $female_term->save();
  $adult_male_term->save();
  $youth_male_term->save();

  // Old prison category IDs => newly created IDs
  $sandbox['prison_categories_map'] = [
    1012 => $female_term->id(),
    1014 => $adult_male_term->id(),
    1011 => $youth_male_term->id(),
  ];
  $prisons_result = \Drupal::entityQuery('taxonomy_term')->condition('vid', 'prisons')->accessCheck(FALSE)->execute();
  $prisons = Term::loadMultiple($prisons_result);
  // Set correct parents of current prisons according to existing prison categories.
  /** @var \Drupal\taxonomy\TermInterface $prison */
  foreach ($prisons as $prison) {
    $prison_category = $prison->get('field_prison_categories')->target_id;
    $new_parent = ['target_id' => $sandbox['prison_categories_map'][$prison_category]];
    $prison->set('parent', $new_parent);
    $prison->save();
  }
}
