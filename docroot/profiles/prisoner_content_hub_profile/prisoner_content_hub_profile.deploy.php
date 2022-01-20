<?php

/**
 * This is a NAME.deploy.php file. It contains "deploy" functions. These are
 * one-time functions that run *after* config is imported during a deployment.
 * These are a higher level alternative to hook_update_n and
 * hook_post_update_NAME functions. See
 * https://www.drush.org/latest/deploycommand/#authoring-update-functions for a
 * detailed comparison.
 */

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

/**
 * Bulk update published content with new prison field.
 */
function prisoner_content_hub_profile_deploy_update_content_to_new_prison_field(&$sandbox) {
  if (!isset($sandbox['progress'])) {
    $sandbox['progress'] = 0;
    $nodes_query = \Drupal::entityQuery('node')
      ->condition('type', 'help_page', '<>')
      ->accessCheck(FALSE);
    $sandbox['result_nodes'] = $nodes_query->execute();

    $terms_query = \Drupal::entityQuery('taxonomy_term')
      ->condition('vid', ['tags', 'series', 'moj_categories'], 'IN')
      ->accessCheck(FALSE);
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
    $new_prison_field_value = [];
    foreach ($prison_category_values as $prison_category_value) {
      $new_prison_field_value[] = ['target_id' => $sandbox['prison_categories_map'][$prison_category_value]];
    }
    prisoner_content_hub_profile_add_categories($entity, $new_prison_field_value, $sandbox);

    $prisons = $entity->get('field_moj_prisons')->referencedEntities();

    /** @var \Drupal\taxonomy\TermInterface $prison */
    foreach ($prisons as $prison) {
      $parent_id = $prison->get('parent')->target_id;
      // Only add prisons that are in categories we have not yet added.
      if (!in_array($parent_id, array_column($new_prison_field_value, 'target_id'))) {
        $new_prison_field_value[] = ['target_id' => $prison->id()];
      }
    }
    $entity->set('field_prisons', $new_prison_field_value);
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

/**
 * Add prison categories to $entity if there are more than 2 prisons within
 * that category.
 *
 * Note this is *not* a deploy hook.
 */
function prisoner_content_hub_profile_add_categories(ContentEntityInterface $entity, array &$new_prison_field_value, array &$sandbox) {
  if ($entity->hasField('field_moj_top_level_categories')) {
    foreach ($entity->get('field_moj_top_level_categories')->getValue() as $category) {
      if ($category['target_id'] == 787) {
        // Do not add extra prison categories for content in Facilities list and catalogues".
        return;
      }
    }
  }

  $prisons = $entity->get('field_moj_prisons')->referencedEntities();
  $prison_category_count = [];
  /** @var \Drupal\taxonomy\TermInterface $prison */
  foreach ($prisons as $prison) {
    $parent_id = $prison->get('parent')->target_id;
    // Add prison categories when there is more than one prison within that
    // category.
    if (!in_array($parent_id, array_column($new_prison_field_value, 'target_id'))) {
      if (!isset($prison_category_count[$parent_id])) {
        $prison_category_count[$parent_id] = 1;
      }
      else {
        $new_prison_field_value[] = ['target_id' => $parent_id];
        $prison_category_count[$parent_id]++;
      }
    }
  }

  // Special handling for Berwyn.
  // If we've added adult male, but Berwyn was not included as a prison,
  // make Berwyn specifically excluded.
  if (isset($prison_category_count[$sandbox['prison_categories_map'][1014]]) && $prison_category_count[$sandbox['prison_categories_map'][1014]] > 1) {
    $berwyn_included = FALSE;
    $berwyn_tid = 792;
    foreach ($prisons as $prison) {
      if ($prison->id() == $berwyn_tid) {
        $berwyn_included = TRUE;
      }
    }
    if (!$berwyn_included) {
      $entity->set('field_exclude_from_prison', [
        ['target_id' => $berwyn_tid],
      ]);
    }
  }
}

/**
 * Copy values from entity reference fields to the new dynamic entity reference
 * fields.
 */
function prisoner_content_hub_profile_deploy_copy_homepage_tile_values() {
  $result = \Drupal::entityQuery('node')
    ->condition('type', 'featured_articles')
    ->accessCheck(FALSE)
    ->execute();
  $nodes = Node::loadMultiple($result);

  /** @var \Drupal\node\NodeInterface $node */
  foreach ($nodes as $node) {
    $tile_large_value = $node->get('field_moj_featured_tile_large')->getValue();
    foreach ($tile_large_value as $k => $v) {
      $tile_large_value[$k]['target_type'] = 'node';
    }
    $node->set('field_featured_tile_large', $tile_large_value);

    $tile_small_value = $node->get('field_moj_featured_tile_small')->getValue();
    foreach ($tile_small_value as $k => $v) {
      $tile_small_value[$k]['target_type'] = 'node';
    }
    $node->set('field_featured_tile_small', $tile_small_value);
    $node->save();
  }
}

/**
 * Set the prison owner for content based on the authors prisons.
 */
function prisoner_content_hub_profile_deploy_set_prison_owner(&$sandbox) {
  if (!isset($sandbox['progress'])) {
    $sandbox['result_nodes'] = \Drupal::entityQuery('node')
      ->condition('type', ['help_page', 'external_link'], 'NOT IN')
      ->accessCheck(FALSE)
      ->execute();

    $sandbox['progress'] = 0;
  }
  $nodes = Node::loadMultiple(array_slice($sandbox['result_nodes'], $sandbox['progress'], 50, TRUE));

  foreach ($nodes as $node) {
    /** @var \Drupal\user\UserInterface $author */
    $author = $node->getOwner();
    $prisons = $author->get('field_user_prisons')->getValue();
    if (!empty($prisons)) {
      $node->set('field_prison_owner', $prisons);
      $node->setNewRevision(TRUE);
      $node->revision_log = 'Automatically updating prison owner based on author of content.';
      $node->setRevisionCreationTime(\Drupal::time()->getRequestTime());

      // Set the user to leon.
      $node->setRevisionUserId(334);
      $node->save();
    }
    $sandbox['progress']++;
  }
  $sandbox['#finished'] = $sandbox['progress'] >= count($sandbox['result_nodes']);
  if ($sandbox['#finished'] ) {
    return 'Completed updated, processed total of: ' . $sandbox['progress'];
  }
  return 'Processed nodes: ' . $sandbox['progress'];
}
