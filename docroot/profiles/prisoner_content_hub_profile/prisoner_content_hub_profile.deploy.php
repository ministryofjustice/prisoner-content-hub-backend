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
 * Copy over values from landing page content types to categories.
 */
function prisoner_content_hub_profile_deploy_copy_landing_page_values() {
  $query = \Drupal::entityQuery('node');
  $query->condition('type', 'landing_page');
  $query->accessCheck(FALSE);
  $result = $query->execute();
  $nodes = Node::loadMultiple($result);

  foreach ($nodes as $node) {
    $referenced_entities = $node->get('field_moj_landing_page_term')->referencedEntities();
    foreach ($referenced_entities as $referenced_entity) {
      /** @var \Drupal\taxonomy\TermInterface $referenced_entity */
      $referenced_entity->set('field_legacy_landing_page', $node->id());
      $referenced_entity->set('field_moj_prisons', $node->get('field_moj_prisons')->getValue());
      $referenced_entity->set('field_prison_categories', $node->get('field_prison_categories')->getValue());
      $referenced_entity->set('description', $node->get('field_moj_description')->getValue());

      $referenced_entity->save();
    }
  }
}

/**
 * Set all Secondary tags to have every prison category.
 */
function prisoner_content_hub_profile_deploy_set_tag_prisons() {
  $query = \Drupal::entityQuery('taxonomy_term');
  $query->condition('vid', 'tags');
  $query->accessCheck(FALSE);
  $result = $query->execute();
  $terms = Term::loadMultiple($result);
  foreach ($terms as $term) {
    /** @var \Drupal\taxonomy\TermInterface $term */
    // Set all four prison category term ids.
    $term->set('field_prison_categories', [1011 => 1011, 1012 => 1012, 1013 => 1013, 1014 => 1014]);
    $term->save();
  }

}


/**
 * Bulk update paths for content and taxonomy terms.
 */
function prisoner_content_hub_profile_deploy_update_paths(&$sandbox) {
  // See https://www.qed42.com/blog/url-alias-update-using-batch-api-drupal-8
  $entities = [];
  $entities['node'] = \Drupal::entityQuery('node')->accessCheck(FALSE)->execute();
  $entities['taxonomy_term'] = \Drupal::entityQuery('taxonomy_term')->condition('vid', ['moj_categories', 'series', 'tags'], 'IN')->execute();
  $result = [];

  foreach ($entities as $type => $entity_list) {
    foreach ($entity_list as $entity_id) {
      $result[] = [
        'entity_type' => $type,
        'id' => $entity_id,
      ];
    }
  }

  // Use the sandbox to store the information needed to track progression.
  if (!isset($sandbox['current']))
  {
    // The count of entities visited so far.
    $sandbox['current'] = 0;
    // Total entities that must be visited.
    $sandbox['max'] = count($result);
    // A place to store messages during the run.
  }

  // Process entities by groups of 20.
  // When a group is processed, the batch update engine determines
  // whether it should continue processing in the same request or provide
  // progress feedback to the user and wait for the next request.
  $limit = 20;
  $result = array_slice($result, $sandbox['current'], $limit);

  foreach ($result as $row) {
    $entity_storage = \Drupal::entityTypeManager()->getStorage($row['entity_type']);
    $entity = $entity_storage->load($row['id']);

    // Update Entity URL alias.
    \Drupal::service('pathauto.generator')->updateEntityAlias($entity, 'update');

    // Update our progress information.
    $sandbox['current']++;
  }

  $sandbox['#finished'] = empty($sandbox['max']) ? 1 : ($sandbox['current'] / $sandbox['max']);

  if ($sandbox['#finished'] >= 1) {
    return 'The batch URL Alias update is finished.';
  }
  else {
    return 'Updated ' . $sandbox['current'] . ' paths';
  }
}

/**
 * Update series to reference categories.
 */
function prisoner_content_hub_profile_deploy_update_series() {
  $result = \Drupal::entityQuery('taxonomy_term')->condition('vid', 'series')->execute();
  $terms = Term::loadMultiple($result);

  /** @var \Drupal\taxonomy\TermInterface $term */
  foreach ($terms as $term) {
    $nodes_result = \Drupal::entityQuery('node')->condition('field_moj_series', $term->id())->execute();
    $nodes = Node::loadMultiple($nodes_result);
    $new_category_values = [];
    /** @var \Drupal\node\NodeInterface $node */
    foreach ($nodes as $node) {
      $category_values = $node->get('field_moj_top_level_categories')->getValue();
      foreach ($category_values as $category_value) {
        $new_category_values[$category_value['target_id']] = $category_value;
      }
    }
    $new_category_values = array_values($new_category_values);
    $term->set('field_category', $new_category_values);
    $term->save();
  }
}
