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
 * Renames terms.
 *
 * @return \Drupal\Core\StringTranslation\TranslatableMarkup
 *   Message displayed to user after update complete.
 */
function prisoner_hub_bulk_updater_deploy_rename_terms() {
  $terms_to_rename = [
    1282 => 'TBC',
    1285 => 'Sentence journey',
    1286 => 'Faith',
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
 * Moves content and taxonomy terms between parent terms.
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
    $child_term_count = 0;
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
    $series_count = 0;
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
