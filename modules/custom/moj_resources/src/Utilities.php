<?php

namespace Drupal\moj_resources;

/**
 * Utilities
 */

class Utilities
{
  public static function filterByPrisonTypes($prison_types, $query) {
    $prison_type_or_group = $query
      ->orConditionGroup()
      ->condition('field_prison_types', $prison_types, 'IN')
      ->condition('field_prison_types', NULL, 'IS NULL')
      ->notExists('field_prison_types');

    $query->condition($prison_type_or_group);

    return $query;
  }
}
