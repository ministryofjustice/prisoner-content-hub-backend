<?php

namespace Drupal\moj_resources;

/**
 * Utilities
 */

class Utilities
{
  public static function filterByPrisonCategories($prisonCategories, $query) {
    $prisonCategoriesOrGroup = $query
      ->orConditionGroup()
      ->condition('field_prison_categories', $prisonCategories, 'IN')
      ->condition('field_prison_categories', NULL, 'IS NULL') // this condition needs to remain in until we do a full launch otherwise no series will work
      ->notExists('field_prison_categories');

    $query->condition($prisonCategoriesOrGroup);

    return $query;
  }

  public static function filterByPrison($prisonId, $query) {
    $prison = $query
      ->orConditionGroup()
      ->condition('field_moj_prisons', $prisonId, '=')
      ->condition('field_moj_prisons', '', '=')
      ->notExists('field_moj_prisons');

    $query->condition($prison);

    return $query;
  }
}
