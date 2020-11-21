<?php

namespace Drupal\moj_resources;

use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

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

  /**
   * Loads prison for ID
   *
   * @param int $prisonId
   * @param EntityTypeManagerInterface $termStoragee
   *
   * @return EntityInterface
  */
  private function getPrison($prisonId, $termStorage) {
    $prison = $termStorage->load($prisonId);

    if (!$prison) {
      throw new NotFoundHttpException(
        'Prison not found',
        null,
        404
      );
    }

    return $prison;
  }

  /**
   * Get Prisons for a Drupal node object
   *
   * @param EntityInterface $node
   * @return int[]
  */
  private function getPrisonsFor($node) {
    $prisons = [];

    foreach ($node->field_moj_prisons as $prison) {
      array_push($prisons, $prison->target_id);
    }

    return $prisons;
  }

  /**
   * Get Prison Categories for a Drupal node object
   *
   * @param EntityInterface $term
   * @return int[]
  */
  private function getPrisonCategoriesFor($node) {
    $prisonCategories = [];

    foreach ($node->field_prison_categories as $prisonCategory) {
      array_push($prisonCategories, $prisonCategory->target_id);
    }

    if (empty($prisonCategories)) {
      throw new BadRequestHttpException(
        'The node does not have any prison categories selected',
        null,
        400
      );
    }

    return $prisonCategories;
  }
}
