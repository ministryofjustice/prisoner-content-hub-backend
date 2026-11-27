<?php

declare(strict_types=1);

namespace Drupal\prisoner_hub_breadcrumbs\Plugin\Field\FieldType;

use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Custom field type for a single piece of a computed breadcrumb.
 */
#[FieldType(
  id: 'computed_breadcrumbs',
  label: new TranslatableMarkup('Breadcrumbs'),
  description: new TranslatableMarkup('Computed breadcrumbs for rendering on the front end application'),
)]
class ComputedBreadcrumbsLinkItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'uri' => [
          'description' => 'The URI of the link.',
          'type' => 'varchar',
          'length' => 2048,
        ],
        'title' => [
          'description' => 'The link text.',
          'type' => 'varchar',
          'length' => 255,
        ],
      ],
      'indexes' => [
        'uri' => [['uri', 30]],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['uri'] = DataDefinition::create('uri')
      ->setLabel(new TranslatableMarkup('URI'));
    $properties['title'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Link text'));

    return $properties;
  }

}
