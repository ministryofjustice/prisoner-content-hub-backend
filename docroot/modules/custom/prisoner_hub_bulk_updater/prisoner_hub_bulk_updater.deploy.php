<?php

/**
 * @file
 * Hooks for updating content following deployments.
 */

use Drupal\Core\Entity\EntityStorageException;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

/**
 * Excludes specific content from Woodhill.
 *
 * @param array $sandbox
 *   Stores information for batch updates.
 *
 * @return string
 *   Message displayed to user after update complete.
 */
function prisoner_hub_bulk_updater_deploy_woodhill_red_content(array &$sandbox): string {
  if (!isset($sandbox['total'])) {
    $sandbox['current'] = 0;

    $module_path = \Drupal::service('extension.list.module')->getPath('prisoner_hub_bulk_updater');
    $red_csv_path = "{$module_path}/files/woodhill_red_nids.csv";
    $red_csv_file = fopen($red_csv_path, 'r');

    $sandbox['nids'] = [];
    while (($line = fgets($red_csv_file)) !== FALSE) {
      if (intval($line) != 0) {
        $sandbox['nids'][] = intval($line);
      }
    };
    fclose($red_csv_file);

    $sandbox['total'] = count($sandbox['nids']);
  }

  $batch_size = 10;
  $batch_count = 0;
  $batch_nids = [];
  while ($batch_count < $batch_size && $sandbox['current'] < $sandbox['total']) {
    $node = Node::load($sandbox['nids'][$sandbox['current']]);
    if ($node) {
      $excluded_prisons = $node->get('field_exclude_from_prison')->getValue() ?? [];
      $excluded_prisons[] = ['target_id' => '1915'];
      $node->set('field_exclude_from_prison', $excluded_prisons);
      $node->setNewRevision(TRUE);
      $node->setRevisionCreationTime(\Drupal::time()->getCurrentTime());
      $node->setRevisionLogMessage('Bulk update to remove content from Woodhill');
      $node->setRevisionUserId(1);
      try {
        $node->save();
        $batch_nids[] = $sandbox['nids'][$sandbox['current']];
      }
      catch (Exception $e) {
        \Drupal::logger('prisoner_hub_bulk_updater')->notice('Exception whilst saving node @nid: @text', [
          '@nid' => $sandbox['nids'][$sandbox['current']],
          '@text' => $e->getMessage(),
        ]);
      }
    }
    $sandbox['current']++;
    $batch_count++;
  }

  $sandbox['#finished'] = (float) $sandbox['current'] / $sandbox['total'];

  return t("Updated @count of @total records\nNode IDs: @nids", [
    '@count' => $sandbox['current'],
    '@total' => $sandbox['total'],
    '@nids' => implode('|', $batch_nids),
  ]);
}

/**
 * Excludes specific content from Cookhamwood.
 *
 * @param array $sandbox
 *   Stores information for batch updates.
 *
 * @return string
 *   Message displayed to user after update complete.
 */
function prisoner_hub_bulk_updater_deploy_cookhamwood_red_content(array &$sandbox): string {
  if (!isset($sandbox['total'])) {
    $sandbox['current'] = 0;

    $module_path = \Drupal::service('extension.list.module')->getPath('prisoner_hub_bulk_updater');
    $red_csv_path = "{$module_path}/files/cookhamwood_red_nids.csv";
    $red_csv_file = fopen($red_csv_path, 'r');

    $sandbox['nids'] = [];
    while (($line = fgets($red_csv_file)) !== FALSE) {
      if (intval($line) != 0) {
        $sandbox['nids'][] = intval($line);
      }
    };
    fclose($red_csv_file);

    $sandbox['total'] = count($sandbox['nids']);
  }

  $batch_size = 10;
  $batch_count = 0;
  $batch_nids = [];
  while ($batch_count < $batch_size && $sandbox['current'] < $sandbox['total']) {
    $node = Node::load($sandbox['nids'][$sandbox['current']]);
    if ($node) {
      $excluded_prisons = $node->get('field_exclude_from_prison')->getValue() ?? [];
      $excluded_prisons[] = ['target_id' => '959'];
      $node->set('field_exclude_from_prison', $excluded_prisons);
      $node->setNewRevision(TRUE);
      $node->setRevisionCreationTime(\Drupal::time()->getCurrentTime());
      $node->setRevisionLogMessage('Bulk update to remove content from Cookhamwood');
      $node->setRevisionUserId(1);
      try {
        $node->save();
        $batch_nids[] = $sandbox['nids'][$sandbox['current']];
      }
      catch (Exception $e) {
        \Drupal::logger('prisoner_hub_bulk_updater')->notice('Exception whilst saving node @nid: @text', [
          '@nid' => $sandbox['nids'][$sandbox['current']],
          '@text' => $e->getMessage(),
        ]);
      }
    }
    $sandbox['current']++;
    $batch_count++;
  }

  $sandbox['#finished'] = (float) $sandbox['current'] / $sandbox['total'];

  return t("Updated @count of @total records\nNode IDs: @nids", [
    '@count' => $sandbox['current'],
    '@total' => $sandbox['total'],
    '@nids' => implode('|', $batch_nids),
  ]);
}

/**
 * Renames terms.
 *
 * @return \Drupal\Core\StringTranslation\TranslatableMarkup
 *   Message displayed to user after update complete.
 */
function prisoner_hub_bulk_updater_deploy_rename_terms() {
  $terms_to_rename = [
    1282 => 'Inspire and entertain',
    1285 => 'Sentence journey',
    1286 => 'Faith',
    1284 => 'Health and wellbeing',
  ];

  $terms_renamed = 0;
  foreach ($terms_to_rename as $term_id => $new_name) {
    if (!$term = Term::load($term_id)) {
      continue;
    }
    try {
      $term->setName($new_name)->save();
      $terms_renamed++;
    }
    catch (EntityStorageException $exception) {
      \Drupal::logger('prisoner_hub_bulk_updater')->warning('Could not save term @id with name @name', [
        '@id' => $term_id,
        '@name' => $new_name,
      ]);
    }
  }
  return t('Renamed @terms_renamed terms.', ['@terms_renamed' => $terms_renamed]);
}

/**
 * Moves content and taxonomy terms from one term to another.
 *
 * The original term is then deleted.
 */
function prisoner_hub_bulk_updater_deploy_move_content() {
  /** @var \Drupal\taxonomy\TermStorageInterface $term_storage */
  $term_storage = \Drupal::service('entity_type.manager')->getStorage('taxonomy_term');

  $content_to_move = [
    649 => 1282,
    1337 => 1285,
  ];

  $logger = \Drupal::logger('prisoner_hub_bulk_updater');

  foreach ($content_to_move as $source_tid => $destination_tid) {
    $moved_content[$source_tid] = [];

    // Move all child terms of the source term to the destination term.
    $child_terms = $term_storage->loadChildren($source_tid);
    foreach ($child_terms as $child_term) {
      $child_term->set('parent', $destination_tid);
      try {
        $child_term->save();
      }
      catch (EntityStorageException $e) {
        $logger->warning('Could not save term @id when trying to move it from @source_tid to @destination_tid', [
          '@id' => $child_term->id(),
          '@source_tid' => $source_tid,
          '@destination_tid' => $destination_tid,
        ]);
      }
    }

    // Move all nodes belonging to the parent content.
    $results = \Drupal::entityQuery('node')
      ->condition('field_moj_top_level_categories', $source_tid)
      ->accessCheck(FALSE)
      ->execute();
    $nodes = Node::loadMultiple($results);
    foreach ($nodes as $node) {
      $node->set('field_moj_top_level_categories', $destination_tid);
      try {
        $node->save();
      }
      catch (EntityStorageException $e) {
        $logger->warning('Could not save node @id when trying to move it from @source_tid to @destination_tid', [
          '@id' => $node->id(),
          '@source_tid' => $source_tid,
          '@destination_tid' => $destination_tid,
        ]);
      }
    }

    // Move all series belonging to the parent content.
    $results = \Drupal::entityQuery('taxonomy_term')
      ->condition('field_category', $source_tid)
      ->accessCheck(FALSE)
      ->execute();
    $terms = Term::loadMultiple($results);
    foreach ($terms as $term) {
      $term->set('field_category', $destination_tid);
      try {
        $term->save();
      }
      catch (EntityStorageException $e) {
        $logger->warning('Could not save term @id when trying to move it from @source_tid to @destination_tid', [
          '@id' => $term->id(),
          '@source_tid' => $source_tid,
          '@destination_tid' => $destination_tid,
        ]);
      }
    }

    // Finally, delete the now-empty source term.
    try {
      $term_storage->delete([Term::load($source_tid)]);
    }
    catch (EntityStorageException $e) {
      $logger->warning('Could not delete now empty term @id', ['@source_tid' => $source_tid]);
    }
  }
}

/**
 * Menu changes.
 */
function prisoner_hub_bulk_updater_deploy_menu_changes() {
  $menus_to_update = [
    'default-primary-navigation',
    'berwyn-primary-navigation',
  ];
  $menu_items_to_rename = [
    18 => 'Inspire and entertain',
    26 => 'Inspire and entertain',
    23 => 'Sentence journey',
    30 => 'Sentence journey',
    22 => 'Health and wellbeing',
    32 => 'Health and wellbeing',
  ];
  $new_menu_items = [
    'Faith' => 1286,
  ];
  $menu_items_orders = [
    [45, 17, 23, 20, 24, 18, 22],
    [25, 30, 29, 27, 26, 32],
  ];

  $logger = \Drupal::logger('prisoner_hub_bulk_updater');

  /** @var \Drupal\menu_link_content\MenuLinkContentStorageInterface $menu_link_content_storage */
  $menu_link_content_storage = \Drupal::service('entity_type.manager')->getStorage('menu_link_content');

  foreach ($menu_items_to_rename as $menu_item_id => $new_title) {
    /** @var \Drupal\menu_link_content\Entity\MenuLinkContent $menu_item */
    $menu_item = $menu_link_content_storage->load($menu_item_id);
    if ($menu_item) {
      $menu_item->set('title', $new_title);
      try {
        $menu_item->save();
      }
      catch (EntityStorageException $e) {
        $logger->warning("Could not rename menu item @menu_item_id", ['@menu_item_id' => $menu_item_id]);
      }
    }
  }

  foreach ($menus_to_update as $menu_name) {
    foreach ($new_menu_items as $title => $target) {
      $new_menu_item = $menu_link_content_storage->create([
        'menu_name' => $menu_name,
        'link' => [
          'uri' => "internal:/tags/$target",
        ],
        'title' => $title,
        'weight' => 10,
      ]);
      try {
        $new_menu_item->save();
      }
      catch (EntityStorageException $e) {
        $logger->warning("Could not add link to @target to menu @menu_name", [
          '@target' => $target,
          '@menu_name' => $menu_name,
        ]);
      }
    }
  }

  foreach ($menu_items_orders as $menu_items_order) {
    $current_weight = 0;
    foreach ($menu_items_order as $menu_item_id) {
      /** @var \Drupal\menu_link_content\Entity\MenuLinkContent $menu_item */
      $menu_item = $menu_link_content_storage->load($menu_item_id);
      if ($menu_item) {
        $menu_item->set('weight', $current_weight);
        try {
          $menu_item->save();
        }
        catch (EntityStorageException $e) {
          $logger->warning("Could not reorder menu item @menu_item_id", ['@menu_item_id' => $menu_item_id]);
        }
        $current_weight++;
      }
    }
  }

}

/**
 * Moves taxonomy terms from one parent to another.
 *
 * The original term and all content within it is preserved.
 */
function prisoner_hub_bulk_updater_deploy_move_terms() {
  /** @var \Drupal\taxonomy\TermStorageInterface $term_storage */
  $term_storage = \Drupal::service('entity_type.manager')->getStorage('taxonomy_term');
  $logger = \Drupal::logger('prisoner_hub_bulk_updater');

  $terms = [
    1286 => NULL,
  ];

  foreach ($terms as $term_id => $new_parent_term_id) {
    $term = $term_storage->load($term_id);
    $term->set('parent', $new_parent_term_id);
    try {
      $term->save();
    }
    catch (EntityStorageException $e) {
      $message = 'Could not move term @term_id to the top level of the vocabulary';
      $context = ['@term_id' => $term_id];
      if ($new_parent_term_id != NULL) {
        $message = 'Could not move term @term_id to be a child of @new_parent_term_id';
        $context['@new_parent_term_id'] = $new_parent_term_id;
      }
      $logger->warning($message, $context);
    }
  }
}

/**
 * Implements cleanse phase 1.
 */
function prisoner_hub_bulk_updater_deploy_cleanse_1(array &$sandbox) {
  if (!isset($sandbox['total'])) {
    $sandbox['current'] = 0;

    $module_path = \Drupal::service('extension.list.module')->getPath('prisoner_hub_bulk_updater');
    $cleanse_csv_path = "{$module_path}/files/cleanse_1.csv";
    $cleanse_csv_file = fopen($cleanse_csv_path, 'r');

    $sandbox['nids'] = [];
    while (($line = fgets($cleanse_csv_file)) !== FALSE) {
      if (intval($line) != 0) {
        $sandbox['nids'][] = intval($line);
      }
    };
    fclose($cleanse_csv_file);

    $sandbox['total'] = count($sandbox['nids']);
  }

  $batch_size = 10;
  $batch_count = 0;
  $batch_nids = [];
  while ($batch_count < $batch_size && $sandbox['current'] < $sandbox['total']) {
    $node = Node::load($sandbox['nids'][$sandbox['current']]);
    if ($node) {
      $node->setUnpublished();
      try {
        $node->save();
        $batch_nids[] = $sandbox['nids'][$sandbox['current']];
      }
      catch (Exception $e) {
        \Drupal::logger('prisoner_hub_bulk_updater')->notice('Exception whilst unpublishing node @nid: @text', [
          '@nid' => $sandbox['nids'][$sandbox['current']],
          '@text' => $e->getMessage(),
        ]);
      }
    }
    $sandbox['current']++;
    $batch_count++;
  }

  $sandbox['#finished'] = (float) $sandbox['current'] / $sandbox['total'];

  return t("Updated @count of @total records\nNode IDs: @nids", [
    '@count' => $sandbox['current'],
    '@total' => $sandbox['total'],
    '@nids' => implode('|', $batch_nids),
  ]);
}

/**
 * Implements cleanse phase 2.
 */
function prisoner_hub_bulk_updater_deploy_cleanse_2(array &$sandbox) {
  if (!isset($sandbox['total'])) {
    $sandbox['current'] = 0;

    $module_path = \Drupal::service('extension.list.module')->getPath('prisoner_hub_bulk_updater');
    $cleanse_csv_path = "{$module_path}/files/cleanse_2.csv";
    $cleanse_csv_file = fopen($cleanse_csv_path, 'r');

    $sandbox['nids'] = [];
    while (($line = fgets($cleanse_csv_file)) !== FALSE) {
      if (intval($line) != 0) {
        $sandbox['nids'][] = intval($line);
      }
    };
    fclose($cleanse_csv_file);

    $sandbox['total'] = count($sandbox['nids']);
  }

  $batch_size = 10;
  $batch_count = 0;
  $batch_nids = [];
  while ($batch_count < $batch_size && $sandbox['current'] < $sandbox['total']) {
    $node = Node::load($sandbox['nids'][$sandbox['current']]);
    if ($node) {
      $node->setUnpublished();
      try {
        $node->save();
        $batch_nids[] = $sandbox['nids'][$sandbox['current']];
      }
      catch (Exception $e) {
        \Drupal::logger('prisoner_hub_bulk_updater')->notice('Exception whilst unpublishing node @nid: @text', [
          '@nid' => $sandbox['nids'][$sandbox['current']],
          '@text' => $e->getMessage(),
        ]);
      }
    }
    $sandbox['current']++;
    $batch_count++;
  }

  $sandbox['#finished'] = (float) $sandbox['current'] / $sandbox['total'];

  return t("Updated @count of @total records\nNode IDs: @nids", [
    '@count' => $sandbox['current'],
    '@total' => $sandbox['total'],
    '@nids' => implode('|', $batch_nids),
  ]);
}

/**
 * Deploy hook to assign comms role to specific accounts.
 *
 * @throws \Drupal\Core\Entity\EntityStorageException
 * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
 * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
 */
function prisoner_hub_bulk_updater_deploy_assign_comms_roles() {
  $comms_uids = [1003, 959, 1001, 1010, 1002, 1033];
  $user_storage = \Drupal::entityTypeManager()->getStorage('user');
  foreach ($comms_uids as $uid) {
    /** @var \Drupal\user\UserInterface $user */
    $user = $user_storage->load($uid);
    $user->addRole('comms_live_service_hq');
    $user->save();
  }
}
