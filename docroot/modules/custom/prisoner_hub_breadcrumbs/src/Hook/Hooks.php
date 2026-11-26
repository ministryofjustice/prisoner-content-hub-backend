<?php

namespace Drupal\prisoner_hub_breadcrumbs\Hook;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for this module.
 */
class Hooks {

  use StringTranslationTrait;

  /**
   * Implements hook_entity_base_field_info().
   */
  #[Hook('entity_base_field_info')]
  public function entityBaseFieldInfo(EntityTypeInterface $entityType): array {
    if ($entityType instanceof ContentEntityTypeInterface === FALSE) {
      return [];
    }

    return [
      'breadcrumbs' => BaseFieldDefinition::create('computed_breadcrumbs')
        ->setName('breadcrumbs')
        ->setLabel($this->t('Breadcrumbs'))
        ->setComputed(TRUE)
        ->setClass('\Drupal\prisoner_hub_breadcrumbs\Field\ComputedBreadcrumbsItemList')
        ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
        ->setDisplayConfigurable('view', TRUE)
        ->setDisplayOptions('view', [
          'label' => 'hidden',
          'region' => 'hidden',
          'weight' => -5,
        ]),
    ];
  }

}
