<?php

namespace Drupal\moj_resources;

use Drupal\Core\Entity\Query\QueryInterface;

/**
 * Utility class for Prisoner Content Hub endpoints
 *
*/
class Utilities {
  /**
    * Add filter by prison categories to a query object
    *
    * @param int $prisonId
    * @param int[] $prisonCategories
    * @param QueryInterface
    *
    * @return QueryInterface
  */
  public static function filterByPrisonCategories($prisonId, $prisonCategories, $query) {
    $filterByPrisonId = $query
      ->orConditionGroup()
      ->condition('field_moj_prisons', $prisonId, '=')
      ->notExists('field_moj_prisons');

    $filterByPrisonCategories = $query
      ->orConditionGroup()
      ->condition('field_prison_categories', $prisonCategories, 'IN')
      ->notExists('field_prison_categories');

    return $query
      ->andConditionGroup()
      ->condition($filterByPrisonCategories)
      ->condition($filterByPrisonId);
  }
}
