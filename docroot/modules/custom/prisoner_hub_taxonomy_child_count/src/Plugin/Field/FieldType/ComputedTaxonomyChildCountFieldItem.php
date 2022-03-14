<?php

namespace Drupal\prisoner_hub_taxonomy_child_count\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'child_term_count' field type.
 *
 * @FieldType(
 *   id = "child_term_count",
 *   label = @Translation("Child term count"),
 *   description = @Translation("Normalized child term counts"),
 *   no_ui = TRUE,
 *   list_class = "\Drupal\prisoner_hub_taxonomy_child_count\Plugin\Field\FieldType\ComputedTaxonomyChildCount",
 * )
 */
class ComputedTaxonomyChildCountFieldItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['sub_categories_count'] = DataDefinition::create('any')
      ->setLabel(t('Sub-categories count'));
    $properties['sub_series_count'] = DataDefinition::create('any')
      ->setLabel(t('Sub-series count'));
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $value = $this->get('sub_categories_count')->getValue();
    return $value === serialize([]);
  }

}
