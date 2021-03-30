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
 * Migrate content from field_moj_tags to field_moj_secondary_tags.
 */
function moj_deploy_migrate_secondary_tag_field_data() {
  $query = \Drupal::entityQuery('node');
  $query->exists('field_moj_tags');
  $query->accessCheck(FALSE);
  $nids = $query->execute();
  $nodes = Node::loadMultiple($nids);
  $count = 0;
  foreach ($nodes as $node) {
    /* @var \Drupal\node\NodeInterface $node */
    $tags = $node->get('field_moj_tags')->getValue();
    $node->set('field_moj_secondary_tags', $tags);

    $node->save();
    $count++;
  }
  return 'Processed ' . $count . ' nodes.';
}
