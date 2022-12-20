<?php

/**
 * This is a NAME.deploy.php file. It contains "deploy" functions. These are
 * one-time functions that run *after* config is imported during a deployment.
 * These are a higher level alternative to hook_update_n and
 * hook_post_update_NAME functions. See
 * https://www.drush.org/latest/deploycommand/#authoring-update-functions for a
 * detailed comparison.
 */

use Drupal\Core\Config\FileStorage;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Site\Settings;
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

/**
 * Copy over field data for taxonomy image field.
 */
function prisoner_content_hub_profile_deploy_copy_thumbnail_image_field_data() {
  // Do this with a direct db query so we don't need to update 3k+ items of
  // content (resulting in a large cache flush).
  \Drupal::database()->query('INSERT INTO taxonomy_term__field_moj_thumbnail_image SELECT * FROM taxonomy_term__field_featured_image');
}

/**
 * Create new homepages and copy over featured tiles.
 */
function prisoner_content_hub_profile_deploy_copy_homepage_data() {
  $results = \Drupal::entityQuery('node')
    ->condition('type', 'featured_articles')
    ->execute();
  $nodes = Node::loadMultiple($results);

  /** @var \Drupal\node\NodeInterface $node */
  foreach ($nodes as $node) {
    $new_homepage = Node::create([
      'type' => 'homepage',
      'title' => $node->label(),
      'field_featured_tiles' => $node->get('field_featured_tile_small')->getValue(),
      'field_prisons' => $node->get('field_prisons')->getValue(),
      'field_exclude_from_prison' => $node->get('field_exclude_from_prison')->getValue(),
      'field_prison_owner' => $node->get('field_prison_owner')->getValue(),
      'uid' => 1,
    ]);
    $new_homepage->setNewRevision(TRUE);
    $node->revision_log = 'Automatically created new homepage, with featured tiles values taken from previous existing "Featured articles" page.';
    $node->setRevisionCreationTime(\Drupal::time()->getRequestTime());
    $node->setRevisionUserId(334);
    $new_homepage->save();
  }
}


/**
 * Re-import the layout builder config, as for some reason this gets altered
 * when it is first enabled.
 */
function prisoner_content_hub_profile_deploy_reimport_layout_builder_config() {
  $config_path = Settings::get('config_sync_directory');
  $source = new FileStorage($config_path);

  $config_storage = \Drupal::service('config.storage');

  $configs = [
    'core.entity_form_display.taxonomy_term.moj_categories.default',
    'core.entity_form_display.taxonomy_term.series.default',
    'core.entity_form_display.taxonomy_term.topics.default',
  ];
  foreach ($configs as $config) {
    $config_storage->write($config, $source->read($config));
  }
}


/**
 * Update series to use release date sorting.
 *
 * For list of series, see https://docs.google.com/spreadsheets/d/1rpF-uYfU2pkTVKTWY6Un4jq9Y8NCNYPfl0_GFXRnUIQ
 */
function prisoner_content_hub_profile_deploy_update_series_to_date_sorting() {
  $series_to_convert = [
    933,
    938,
    940,
    947,
    964,
    965,
    978,
    989,
    1100,
    1106,
    1108,
    1119,
    1168,
    1172,
    1183,
    1189,
    1247,
    1254,
    1255,
    1256,
    1319,
    1320,
    1323,
    1324,
    1347,
    1348,
    1353,
    1382,
    1414,
    1416,
    1427,
    1441,
    1445,
    1451,
    1475,
    1478,
    1485,
    1524,
    1529,
    1536,
  ];
  foreach ($series_to_convert as $series_id) {
    $term = Term::load($series_id);
    $term->set('field_sort_by', 'release_date_desc');
    $term->save();

    $previous_content_date = NULL;
    $result = \Drupal::entityQuery('node')
      ->condition('field_moj_series', $series_id)
      ->sort('series_sort_value')
      ->sort('created', 'DESC')
      ->accessCheck(FALSE)
      ->execute();
    $nodes = Node::loadMultiple($result);

    // Set the release date value for content (this won't exist as it was
    // previously using episode numbers).
    foreach ($nodes as $node) {
      $date = NULL;

      // Try to establish date from the title, as often this is put there.

      // Format NPR Friday | 29 July | NPR
      // OR NPR Friday | 29 July 2022 | NPR
      $parts = explode('|', $node->label());
      if (isset($parts[1])) {
        $date_string = trim($parts[1]);
        if (is_numeric(substr($date_string, -4))) {
          $date = strtotime($date_string);
          if ($date) {
            \Drupal::messenger()->addMessage('Converted ' . $date_string);
          }
        }
        else {
          $date = strtotime($date_string . ' ' . date('Y', $node->get('created')->value));
          if ($date) {
            \Drupal::messenger()
              ->addMessage('Converted ' . $date_string . ' with year ' . date('Y', $node->get('created')->value));
          }
        }
      }
      if (!$date) {
        // Format NPR Sunday Service: 3 May
        // OR Youth Council Meeting 3rd March 2022
        $parts = explode(':', $node->label());
        if (isset($parts[1])) {
          $date_string = trim($parts[1]);
          if (is_numeric(substr($date_string, -4))) {
            $date = strtotime($date_string);
            if ($date) {
              \Drupal::messenger()->addMessage('Converted ' . $date_string);
            }
          }
          else {
            $date = strtotime($date_string . ' ' . date('Y', $node->get('created')->value));
            if ($date) {
              \Drupal::messenger()
                ->addMessage('Converted ' . $date_string . ' with year ' . date('Y', $node->get('created')->value));
            }
          }
        }
      }

      // If no date fround from title, use the created date.
      if (!$date) {
        $date = $node->get('created')->value;
      }

      // If node is published, we want to ensure it keeps the existing order.
      if ($node->isPublished()) {
        // To ensure the existing order is retained, overwrite the date if the new
        // one places this before the previous content item.
        if ($previous_content_date && $date > $previous_content_date) {
          $date = $previous_content_date - 86400;
        }

        $previous_content_date = $date;
      }

      $node->set('field_release_date', date('Y-m-d', $date));
      $node->save();
    }
  }
}

/**
 * Convert series to subcategories.
 *
 * For list of series, see https://docs.google.com/spreadsheets/d/1rpF-uYfU2pkTVKTWY6Un4jq9Y8NCNYPfl0_GFXRnUIQ
 */
function prisoner_content_hub_profile_deploy_convert_series_to_subcats() {
  $series_to_convert = [
    1413,
    1490,
  ];

  $terms = Term::loadMultiple($series_to_convert);

  foreach ($terms as $term) {

    // Create a new category and copy all over all the previous values.
    $new_category = Term::create([
      'vid' => 'moj_categories',
      'name' => $term->label(),
      'description' => $term->get('description')->getValue(),
      'field_moj_thumbnail_image' => $term->get('field_moj_thumbnail_image')->getValue(),
      'field_is_homepage_updates' => $term->get('field_is_homepage_updates')->getValue(),
      'field_exclude_feedback' => $term->get('field_exclude_feedback')->getValue(),
      'field_prisons' => $term->get('field_prisons')->getValue(),
      'field_exclude_from_prison' => $term->get('field_exclude_from_prison')->getValue(),
      'parent' => $term->get('field_category')->getValue(),
    ]);
    $new_category->save();

    // Now update all the content assigned to the series to now be assigned
    // the category.
    $result = Drupal::entityQuery('node')
      ->condition('field_moj_series', $term->id())
      ->accessCheck(FALSE)
      ->execute();
    $nodes = Node::loadMultiple($result);
    foreach ($nodes as $node) {
      $node->set('field_moj_top_level_categories', [
        ['target_id' => $new_category->id()]
      ]);
      $node->set('field_moj_series', NULL);
      $node->set('field_not_in_series', 1);
      $node->revision_log = 'Bulk update, converting series to subcategories, automatically updating content to be assigned to the new subcategory.';
      $node->setRevisionCreationTime(\Drupal::time()->getRequestTime());
      $node->setRevisionUserId(334);
      $node->save();
    }

    // Update any embedded links to the old series, as the id and url have
    // now changed.
    $find = 'tags/' . $term->id();
    $replace = 'tags/' . $new_category->id();
    \Drupal::database()->query("UPDATE node__field_moj_description SET field_moj_description_value = REPLACE(field_moj_description_value, '" . $find . "', '" . $replace . "') WHERE field_moj_description_value LIKE '%" . $find . "%'");
    $term->delete();

  }
}

/**
 * Copy summaries from the description field to the new summary field
 */
function prisoner_content_hub_profile_deploy_copy_summary_to_new_field(&$sandbox) {
  if (!isset($sandbox['progress'])) {
    $sandbox['result'] = \Drupal::entityQuery('node')
      ->exists('field_moj_description')
      ->accessCheck(FALSE)
      ->execute();
    $sandbox['progress'] = 0;
  }

  $nodes = Node::loadMultiple(array_slice($sandbox['result'], $sandbox['progress'], 25, TRUE));

  /** @var \Drupal\node\NodeInterface $node */
  foreach ($nodes as $node) {
    $sandbox['progress']++;
    if ($node->hasField('field_moj_description')) {
      $updated = FALSE;
      $summary = $node->field_moj_description->summary;
      if (!empty($summary)) {
        $node->set('field_summary', $summary);
        $updated = TRUE;
      }
      if ($node->bundle() == 'page' && !empty($node->field_moj_description->value)) {
        $node->set('field_main_body_content', $node->field_moj_description->value);
        $updated = TRUE;
      }
      if ($node->bundle() == 'moj_radio_item' || $node->bundle() == 'moj_video_item') {
        $node->set('field_description', $node->field_moj_description->value);
        $updated = TRUE;
      }
      if ($updated) {
        $node->save();
      }
    }
  }
  $sandbox['#finished'] = $sandbox['progress'] >= count($sandbox['result']);
  if ($sandbox['#finished'] ) {
    return 'Completed updated, processed total of: ' . $sandbox['updated'];
  }
  return 'Updated nodes: ' . $sandbox['progress'];
}
