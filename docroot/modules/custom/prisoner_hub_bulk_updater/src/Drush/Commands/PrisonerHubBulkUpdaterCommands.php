<?php

namespace Drupal\prisoner_hub_bulk_updater\Drush\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A Drush commandfile for the bulk updater.
 */
final class PrisonerHubBulkUpdaterCommands extends DrushCommands {

  /**
   * Constructs a PrisonerHubBulkUpdaterCommands object.
   */
  public function __construct(
    private readonly ModuleExtensionList $extensionListModule,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TimeInterface $time,
  ) {
    parent::__construct();
  }

  /**
   * Factory method.
   */
  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('extension.list.module'),
      $container->get('entity_type.manager'),
      $container->get('datetime.time'),
    );
  }

  /**
   * Excludes a set list of content from a specific prison.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  #[CLI\Command(name: 'prisoner_hub_bulk_updater:apply-red-list', aliases: ['pharl'])]
  #[CLI\Argument(name: 'prison', description: 'Machine name of the prison to which to apply the red list.')]
  #[CLI\Argument(name: 'list', description: 'Name of the CSV file in this module\'s files folder that comprises the red list.')]
  #[CLI\FieldLabels(labels: [
    'nid' => 'Node ID',
    'status' => 'Status',
  ])]
  #[CLI\DefaultTableFields(fields: ['nid', 'status'])]
  #[CLI\Usage(name: 'prisoner_hub_bulk_updater:apply-red-list chelmsford cookhamwood_red_nids.csv', description: 'Usage description')]
  public function commandName($prison, $list): RowsOfFields {
    // First check we have a valid prison, and a readable csv file.
    $module_path = $this->extensionListModule->getPath('prisoner_hub_bulk_updater');
    $red_csv_path = "{$module_path}/files/{$list}";
    if (!is_readable($red_csv_path)) {
      throw new \InvalidArgumentException("The file $red_csv_path does not exist, or is not readable.");
    }
    $red_csv_file = fopen($red_csv_path, 'r');
    if (!$red_csv_file) {
      throw new \InvalidArgumentException("Could not open $red_csv_file for reading.");
    }

    $prison_term = taxonomy_machine_name_term_load($prison, 'prisons');
    if (!$prison_term) {
      throw new \InvalidArgumentException("There is no prison with the machine name {$prison}.");
    }

    $rows = [];

    // Arguments are valid, so proceed to exclude content.
    $node_storage = $this->entityTypeManager->getStorage('node');

    // For every line in the CSV...
    while (($line = fgets($red_csv_file)) !== FALSE) {
      $nid = intval($line);
      // ...check this line has a valid node id, and skip if not.
      if (!$nid) {
        $rows[] = [
          'nid' => $line,
          'status' => 'Non-numeric node ID',
        ];
        continue;
      }
      /** @var \Drupal\Node\NodeInterface $node */
      $node = $node_storage->load($nid);
      if (!$node) {
        $rows[] = [
          'nid' => $nid,
          'status' => 'Could not be loaded',
        ];
        continue;
      }
      $excluded_prisons = $node->get('field_exclude_from_prison')->getValue() ?? [];
      // Don't edit nodes that are already excluded from this prison.
      $already_excluded = FALSE;
      if (is_array($excluded_prisons)) {
        foreach ($excluded_prisons as $excluded_prison) {
          if ($excluded_prison['target_id'] == $prison_term->id()) {
            $already_excluded = TRUE;
            break;
          }
        }
      }
      if ($already_excluded) {
        $rows[] = [
          'nid' => $nid,
          'status' => "Already excluded from " . $prison_term->label(),
        ];
      }
      if (!$already_excluded) {
        $excluded_prisons[] = ['target_id' => $prison_term->id()];
        $node->set('field_exclude_from_prison', $excluded_prisons);
        $node->setNewRevision(TRUE);
        $node->setRevisionCreationTime($this->time->getCurrentTime());
        $node->setRevisionLogMessage('Bulk update to remove content from ' . $prison_term->label());
        $node->setRevisionUserId(1);
        try {
          $node->save();
          $rows[] = [
            'nid' => $nid,
            'status' => "Successfully excluded from " . $prison_term->label(),
          ];
        }
        catch (EntityStorageException $e) {
          $rows[] = [
            'nid' => $nid,
            'status' => "Could not be saved",
          ];
        }
      }
    }

    return new RowsOfFields($rows);
  }

}