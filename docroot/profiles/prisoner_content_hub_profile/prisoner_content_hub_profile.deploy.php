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

/**
 * Update series with missing images, copy over the most recent image from an
 * episode in the series.
 */
function prisoner_content_hub_profile_deploy_update_series_images() {
  $result = \Drupal::entityQuery('taxonomy_term')
    ->condition('vid', 'series')
    ->condition('field_featured_image', NULL,'IS NULL')
    ->accessCheck(FALSE)
    ->execute();

  $terms = Term::loadMultiple($result);

  /** @var \Drupal\taxonomy\TermInterface $term */
  foreach ($terms as $term) {
    $result = \Drupal::entityQuery('node')
      ->condition('field_moj_series', $term->id())
      ->condition('field_moj_thumbnail_image', NULL, 'IS NOT NULL')
      ->range(0, 1)
      ->sort('created', 'DESC')
      ->execute();
    if (!empty($result)) {
      $node = Node::load(reset($result));
      $image_field_value = $node->get('field_moj_thumbnail_image')->getValue();
      $term->set('field_featured_image', $image_field_value);
      $term->save();
      print 'Updated term id: ' . $term->id() . ' name: ' . $term->label() . PHP_EOL;
    }
    else {
      print 'Unable to update (missing image)) term id: ' . $term->id() . ' name: ' . $term->label() . PHP_EOL;
    }
  }
}

/**
 * Update content without a prison owner that is only assigned to one prison.
 */
function prisoner_content_hub_profile_deploy_update_prison_owners(&$sandbox) {
  if (!isset($sandbox['progress'])) {
    $sandbox['result'] = \Drupal::entityQuery('node')
      ->condition('field_prison_owner', NULL, 'IS NULL')
      ->accessCheck(FALSE)
      ->execute();

    $sandbox['progress'] = 0;
    $sandbox['updated'] = 0;
  }
  $nodes = Node::loadMultiple(array_slice($sandbox['result'], $sandbox['progress'], 50, TRUE));

  /** @var \Drupal\node\NodeInterface $node */
  foreach ($nodes as $node) {
    $sandbox['progress']++;
    if (!$node->hasField('field_prisons') || !$node->hasField('field_prison_owner')) {
      continue;
    }
    $prisons = $node->get('field_prisons')->referencedEntities();
    if (count($prisons) === 1) {
      $prison = reset($prisons);
      // Prison needs to have a parent, i.e. not be a prison category itself.
      if ($prison->parent->target_id != "0") {
        $node->set('field_prison_owner', [
          ['target_id' => $prison->id()],
        ]);
        $node->setNewRevision(TRUE);
        $node->revision_log = 'Bulk updating prison owner for content only available in one prison.';
        $node->setRevisionCreationTime(\Drupal::time()->getRequestTime());
        $node->save();
        $sandbox['updated']++;
        print 'Updated node: ' . $node->id() . ' title: ' . $node->label() . PHP_EOL;
      }
    }
  }
  $sandbox['#finished'] = $sandbox['progress'] >= count($sandbox['result']);
  if ($sandbox['#finished'] ) {
    return 'Completed updated, processed total of: ' . $sandbox['updated'];
  }
  return 'Updated nodes: ' . $sandbox['progress'];
}

/**
 * Copy over content from "external_link" to "link" content type.
 */
function prisoner_content_hub_profile_deploy_copy_link_content_type() {
  $result = \Drupal::entityQuery('node')
    ->accessCheck(FALSE)
    ->condition('type', 'external_link')
    ->execute();

  $nodes = Node::loadMultiple($result);

  /** @var \Drupal\node\NodeInterface $node */
  foreach ($nodes as $node) {
    $node_values = $node->toArray();
    $node_values['type'] = 'link';
    unset($node_values['nid']);
    unset($node_values['uuid']);
    unset($node_values['vid']);
    unset($node_values['path']);

    $node_values['field_url'] = [
      ['value' => $node_values['field_external_url'][0]['uri']],
    ];
    unset($node_values['field_external_url']);

    $node_values['field_show_interstitial_page'] = 1;

    $new_node = Node::create($node_values);
    $new_node->save();
  }
}


/**
 * Convert secondary tags to topics.
 */
function prisoner_content_hub_profile_deploy_copy_secondary_tags() {
  $result = \Drupal::entityQuery('taxonomy_term')
    ->condition('vid', 'tags')
    ->accessCheck(FALSE)
    ->execute();


  $terms = Term::loadMultiple($result);
  /** @var \Drupal\taxonomy\TermInterface $term */
  foreach ($terms as $term) {
    $term->set('vid', 'topics');
    $term->save();
  }
}


/**
 * Copy over field data for sec tags to topics.
 */
function prisoner_content_hub_profile_deploy_copy_secondary_tag_field_data() {
  // Do this with a direct db query so we don't need to update 3k+ items of
  // content (resulting in a large cache flush).
  \Drupal::database()->query('INSERT INTO node__field_topics SELECT * FROM node__field_moj_secondary_tags');
  \Drupal::database()->query('INSERT INTO node_revision__field_topics SELECT * FROM node_revision__field_moj_secondary_tags');
}
