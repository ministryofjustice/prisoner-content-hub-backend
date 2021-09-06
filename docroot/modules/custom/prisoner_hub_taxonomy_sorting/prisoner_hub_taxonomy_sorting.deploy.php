<?php

/**
 * This is a NAME.deploy.php file. It contains "deploy" functions. These are
 * one-time functions that run *after* config is imported during a deployment.
 * These are a higher level alternative to hook_update_n and hook_post_update_NAME
 * functions. See https://www.drush.org/latest/deploycommand/#authoring-update-functions
 * for a detailed comparison.
 */

use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

/**
 * Update existing Taxonomy terms with default values.
 */
function prisoner_hub_taxonomy_sorting_deploy_set_field_default_values() {
  $query = \Drupal::entityQuery('taxonomy_term');
  $query->condition('vid', 'series');
  $result = $query->execute();
  $taxonomy_terms = Term::loadMultiple($result);
  foreach ($taxonomy_terms as $term) {
    /* @var \Drupal\Taxonomy\TermInterface $term */
    if (empty($term->get('field_sort_by')->getValue())) {
      $term->set('field_sort_by', 'season_and_episode_desc');
      $term->save();
    }
  }
}


/**
 * Copy values from field_moj_date to field_release_date.
 */
function prisoner_hub_taxonomy_sorting_deploy_copy_moj_date_2() {
  // @See https://drupal.stackexchange.com/a/250937/4831
  Drupal::database()->query("INSERT INTO node__field_release_date SELECT * FROM node__field_moj_date;");
  Drupal::database()->query("INSERT INTO node_revision__field_release_date SELECT * FROM node_revision__field_moj_date;");
}

/**
 * Bulk populate values for series_sort_value field.
 */
function prisoner_hub_taxonomy_sorting_deploy_set_series_value_field(&$sandbox) {
  if (!isset($sandbox['progress'])) {
    $sandbox['progress'] = 0;
    $query = \Drupal::entityQuery('node');
    // Get all nodes tagged with "Youth female".
    $query->exists('field_moj_series');
    $sandbox['result'] = $query->execute();
  }

  $nodes = Node::loadMultiple(array_slice($sandbox['result'], $sandbox['progress'], 100, TRUE));

  foreach ($nodes as $node) {
    /** @var \Drupal\node\NodeInterface $node */
    // Resave the node to invoke hook_entity_presave().
    $node->save();
    $sandbox['progress']++;
  }
  $sandbox['#finished'] = $sandbox['progress'] >= count($sandbox['result']);
  if ($sandbox['#finished'] ) {
    return 'Completed updated, processed total of: ' . $sandbox['progress'];
  }
  return 'Processed nodes: ' . $sandbox['progress'];
}


