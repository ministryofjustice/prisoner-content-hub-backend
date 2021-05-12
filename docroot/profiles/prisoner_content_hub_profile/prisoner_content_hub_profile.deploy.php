<?php

/**
 * This is a NAME.deploy.php file. It contains "deploy" functions. These are
 * one-time functions that run *after* config is imported during a deployment.
 * These are a higher level alternative to hook_update_n and hook_post_update_NAME
 * functions. See https://www.drush.org/latest/deploycommand/#authoring-update-functions
 * for a detailed comparison.
 */


use Drupal\node\Entity\Node;

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

      $referenced_entity->save();
    }
  }
}
