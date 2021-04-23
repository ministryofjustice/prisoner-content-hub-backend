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

