<?php

namespace Drupal\prisoner_hub_taxonomy_field_ux\Plugin\views\field;

use Drupal\taxonomy\TermInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * A views field handler to show the parent category for both subcategories and
 * series.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("dynamic_parent_category")
 */
class DynamicParentCategoryViewsField extends FieldPluginBase {

  function getValue(ResultRow $values, $field = NULL) {
    if ($values->_entity instanceof TermInterface) {
      if ($values->_entity->hasField('field_category')) {
        return $values->_entity->field_category->target_id;
      }
      else {
        return $values->_entity->parent->target_id;
      }
    }
    return parent::getValue($values, $field);
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // This function exists to override parent query function.
    // Do nothing.
  }
}
