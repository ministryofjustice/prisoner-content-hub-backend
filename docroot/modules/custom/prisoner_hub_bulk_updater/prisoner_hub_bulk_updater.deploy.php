<?php

/**
 * @file
 * Hooks for updating content following deployments.
 */

use Drupal\node\Entity\Node;

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
