<?php

namespace Drupal\computed_taxonomy_child_count\Field;

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
    $result = \Drupal::entityQuery('taxonomy_term')
      ->condition('parent', $entity->id())
      ->count()
      ->execute();
    $this->list[0] = $this->createItem(0, $result);
  }
}
