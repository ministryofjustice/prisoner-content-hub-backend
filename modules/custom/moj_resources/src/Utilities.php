<?php

namespace Drupal\moj_resources;

use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

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
   * Loads term for ID
   *
   * @param int $termId
   * @param EntityTypeManagerInterface $termStorage
   *
   * @return EntityInterface
  */
  public static function getTermFor($termId, $termStorage) {
    $term = $termStorage->load($termId);

    if (!$term) {
      throw new NotFoundHttpException(
        'Term not found',
        null,
        404
      );
    }

    return $term;
  }

  /**
   * Loads node for ID
   *
   * @param int $nodeId
   * @param EntityTypeManagerInterface $nodeStorage
   *
   * @return EntityInterface
  */
  public static function getNodeFor($nodeId, $nodeStorage) {
    $node = $nodeStorage->load($nodeId);

    if (!$node) {
      throw new NotFoundHttpException(
        'Node not found',
        null,
        404
      );
    }

    return $node;
  }

  /**
   * Get Prisons for a Drupal node object
   *
   * @param EntityInterface $node
   * @return int[]
  */
  public static function getPrisonsFor($node) {
    $prisons = array();

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
  public static function getPrisonCategoriesFor($node) {
    $prisonCategories = array();

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
