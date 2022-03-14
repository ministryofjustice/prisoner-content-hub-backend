<?php

namespace Drupal\prisoner_hub_taxonomy_child_count\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\TypedData\ComputedItemListTrait;

class ComputedTaxonomyChildCount extends FieldItemList implements FieldItemListInterface {
  use ComputedItemListTrait;

  /**
   * @inheritDoc
   */
  function computeValue() {
    $entity = $this->getEntity();
    if ($entity->isNew()) {
      return NULL;
    }
    $result = [];
    $result['sub_categories_count'] = \Drupal::entityQuery('taxonomy_term')
      ->condition('parent', $entity->id())
      ->count()
      ->execute();
    $result['sub_series_count'] = \Drupal::entityQuery('taxonomy_term')
      ->condition('field_category', $entity->id())
      ->count()
      ->execute();
    $this->list[0] = $this->createItem(0, $result);
  }
}
