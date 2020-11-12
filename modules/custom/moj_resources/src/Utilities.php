<?php

namespace Drupal\moj_resources;

/**
 * Utilities
 */

class Utilities
{
  public static function filterByPrisonCategories($prison_categories, $query) {
    $prison_categories_or_group = $query
      ->orConditionGroup()
      ->condition('field_prison_categories', $prison_categories, 'IN')
      ->condition('field_prison_categories', NULL, 'IS NULL') // this condition needs to remain in until we do a full launch otherwise no series will work
      ->notExists('field_prison_categories');

    $query->condition($prison_categories_or_group);

    return $query;
  }

  public static function filterByPrison($prison_id, $query) {
    $prison = $query
      ->orConditionGroup()
      ->condition('field_moj_prisons', $prison_id, '=')
      ->condition('field_moj_prisons', '', '=')
      ->notExists('field_moj_prisons');

    $query->condition($prison);

    return $query;
  }
}
