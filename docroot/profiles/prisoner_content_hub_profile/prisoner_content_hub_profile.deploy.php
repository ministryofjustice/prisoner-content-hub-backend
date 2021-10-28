<?php

/**
 * This is a NAME.deploy.php file. It contains "deploy" functions. These are
 * one-time functions that run *after* config is imported during a deployment.
 * These are a higher level alternative to hook_update_n and
 * hook_post_update_NAME functions. See
 * https://www.drush.org/latest/deploycommand/#authoring-update-functions for a
 * detailed comparison.
 */


use Drupal\Core\Url;
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
  $terms_result = \Drupal::entityQuery('taxonomy_term')->condition('vid', 'series')->accessCheck(FALSE)->execute();
  $terms = Term::loadMultiple($terms_result);
  $nodes_result = \Drupal::entityQuery('node')->exists('field_moj_series')->accessCheck(FALSE)->execute();
  $nodes = Node::loadMultiple($nodes_result);

  $new_category_values = [];
  /** @var \Drupal\node\NodeInterface $node */
  foreach ($nodes as $node) {
    foreach ($node->get('field_moj_top_level_categories')->getValue() as $value) {
      $new_category_values[$node->field_moj_series->target_id][] = $value['target_id'];
    }
  }

  foreach ($new_category_values as $term_id => $category_values) {
    if (isset($terms[$term_id])) {
      $new_category_field_value = [];
      foreach (array_unique($category_values) as $category_value) {
        $new_category_field_value[] = ['target_id' => $category_value];
      }
      $term = $terms[$term_id];
      $term->set('field_category', $new_category_field_value);
      $term->save();
    }
  }
}

/**
 * Update content based on if it has a series.
 *
 * Content with a series, will have its category removed, and field_not_in_series set to 0.
 * Content not in a series, will have field_not_in_series set to 1.
 */
function prisoner_content_hub_profile_deploy_remove_categories_from_content_with_series(&$sandbox) {
  if (!isset($sandbox['progress'])) {
    $sandbox['progress'] = 0;
    $query = \Drupal::entityQuery('node');
    // Perform on unpublished nodes.
    $query->condition('type', ['moj_radio_item', 'page', 'moj_video_item', 'moj_pdf_item'], 'IN');
    $query->accessCheck(FALSE);
    $sandbox['result'] = $query->execute();
  }

  $nodes = Node::loadMultiple(array_slice($sandbox['result'], $sandbox['progress'], 100, TRUE));

  /** @var \Drupal\node\NodeInterface $node */
  foreach ($nodes as $node) {
    $series_entities = $node->get('field_moj_series')->referencedEntities();
    if (empty($series_entities)) {
      $node->set('field_not_in_series', 1);
      // Ensure all series related fields are blank.
      $node->set('field_moj_series', NULL);
      $node->set('field_moj_episode', NULL);
      $node->set('field_moj_season', NULL);
      $node->set('field_release_date', NULL);
    }
    else {
      $node->set('field_moj_top_level_categories', NULL);
      $node->set('field_not_in_series', 0);
    }
    $node->save();
    $sandbox['progress']++;
  }

  $sandbox['#finished'] = $sandbox['progress'] >= count($sandbox['result']);
  if ($sandbox['#finished'] ) {
    return 'Completed updated, processed total of: ' . $sandbox['progress'];
  }
  return 'Processed nodes: ' . $sandbox['progress'];

}

/**
 * Update description field on series to have the content from summary field.
 */
function prisoner_content_hub_profile_deploy_copy_summary(&$sandbox) {
  if (!isset($sandbox['progress'])) {
    $sandbox['progress'] = 0;
    $sandbox['result'] = $result = \Drupal::entityQuery('taxonomy_term')->condition('vid', 'series')->execute();
  }

  $terms = Term::loadMultiple(array_slice($sandbox['result'], $sandbox['progress'], 100, TRUE));

  /** @var \Drupal\taxonomy\TermInterface $term */
  foreach ($terms as $term) {
    $term->set('description', $term->get('field_content_summary')->getValue());
    $term->save();
    $sandbox['progress']++;
  }

  $sandbox['#finished'] = $sandbox['progress'] >= count($sandbox['result']);
  if ($sandbox['#finished'] ) {
    return 'Completed updated, processed total of: ' . $sandbox['progress'];
  }
  return 'Processed terms: ' . $sandbox['progress'];
}

/**
 * Bulk update categories to have featured content tiles.
 */
function prisoner_content_hub_profile_deploy_category_tiles() {
  $result = \Drupal::entityQuery('taxonomy_term')->condition('vid', 'moj_categories')->execute();
  $terms = Term::loadMultiple($result);

  /** @var \Drupal\taxonomy\TermInterface $term */
  foreach ($terms as $term) {
    $featured_tiles_value = [];
    $featured_content = \Drupal::service('moj_resources.category_featured_content_api_class')->CategoryFeaturedContentApiEndpoint($term->id(), 50, 0);
    foreach ($featured_content as $item) {
      $featured_tiles_value[] = [
        'target_id' => $item['id'],
        'target_type' => $item['content_type'] == 'series' ? 'taxonomy_term' : 'node',
      ];
    }
    $term->set('field_featured_tiles', $featured_tiles_value);
    $term->save();
  }
}

/**
 * Re-deploy the update series job, to account for updates since it was last run.
 */
function prisoner_content_hub_profile_deploy_update_series_redeploy() {
  prisoner_content_hub_profile_deploy_update_series();
}

/**
 * Re-run the featured content update, to update to the latest content.
 */
function prisoner_content_hub_profile_deploy_category_tiles_redeploy() {
  prisoner_content_hub_profile_deploy_category_tiles();
}
