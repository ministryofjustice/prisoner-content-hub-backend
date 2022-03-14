<?php

namespace Drupal\prisoner_hub_taxonomy_child_count\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\taxonomy\Entity\Term;

/**
 * Represents the computed child_term_count field.
 */
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

    $result['sub_categories_count'] = 0;
    $sub_categories_result = \Drupal::entityQuery('taxonomy_term')
      ->condition('parent', $entity->id())
      ->execute();
    /** @var \Drupal\taxonomy\TermInterface $term */
    foreach (Term::loadMultiple($sub_categories_result) as $term) {
      if ($term->access('view')) {
        $result['sub_categories_count']++;
      }
    }

    $result['sub_series_count'] = 0;
    $sub_series_result = \Drupal::entityQuery('taxonomy_term')
      ->condition('field_category', $entity->id())
      ->execute();
    /** @var \Drupal\taxonomy\TermInterface $term */
    foreach (Term::loadMultiple($sub_series_result) as $term) {
      if ($term->access('view')) {
        $result['sub_series_count']++;
      }
    }

    $this->list[0] = $this->createItem(0, $result);
  }
}
