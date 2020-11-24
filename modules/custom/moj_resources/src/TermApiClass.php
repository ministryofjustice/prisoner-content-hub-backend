<?php

namespace Drupal\moj_resources;

use Drupal\node\NodeInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * PromotedContentApiClass
*/

class TermApiClass {
  /**
   * TermStorage object
   *
   * @var EntityManagerInterface
  */
  protected $termStorage;

  /**
   * Class Constructor
   *
   * @param EntityTypeManager $entityTypeManager
   * @param QueryFactory $entityQuery
  */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    QueryFactory $entityQuery
  ) {
    $this->termStorage = $entityTypeManager->getStorage('taxonomy_term');
  }

  /**
   * API resource function
   *
   * @param int $termId
   *
   * @return array
  */
  public function TermApiEndpoint($termId, $prisonId) {

    $term = $this->getTerm($termId, $prisonId);
    return $this->createReturnObject($term);
  }

  private function getTerm($termId, $prisonId) {
    $prison = $this->getPrison($prisonId);
    $term = $this->termStorage->load($termId);

    if (is_null($term)) {
      throw new NotFoundHttpException(
        'Term not found',
        null,
        404
      );
    }

    if ($term->hasField('field_promoted_to_prison')) {

      $hasPrisonSelected = !$term->get('field_promoted_to_prison')->isEmpty();
      $selectedPrisonDoesNotMatchRequest = $term->get('field_promoted_to_prison')->target_id !== $prisonId;

      if ($hasPrisonSelected && $selectedPrisonDoesNotMatchRequest) {
        throw new BadRequestHttpException(
          'The prison for the term does no match the supplied prison',
          null,
          400
        );
      }

      return $term;

    }

    if ($term->hasField('field_prison_categories')
      && !$term->get('field_prison_categories')->isEmpty()) {

      $termPrisonCategories = $term->get('field_prison_categories');
      $prisonCategories = $prison->get('field_prison_categories');

      $prisonCategories = [];

      foreach($prison->get('field_prison_categories') as $prison_category) {
        array_push($prisonCategories, $prison_category->target_id);
      }

      $termPrisonCategories = [];

      foreach($term->get('field_prison_categories') as $prison_category) {
        array_push($termPrisonCategories, $prison_category->target_id);
      }

      $matchingPrisonCategories = array_intersect($prisonCategories, $termPrisonCategories);
      $hasNoMatchingPrisonCategories = empty($matchingPrisonCategories);

      if ($hasNoMatchingPrisonCategories) {
        throw new BadRequestHttpException(
          'The Term does not have a matching prison category for this prison',
          null,
          400
        );
      }
    }

    return $term;
  }

  private function getPrison($prisonId) {
    $prison = $this->termStorage->load($prisonId);

    if(is_null($prison)) {
      throw new BadRequestHttpException(
        'Prison does not exist',
        null,
        400
      );
    }

    return $prison;
  }

  /**
   * Decorate term response
   *
   * @param NodeInterface $ter
   *
   * @return array
  */
  private function createReturnObject($term) {
    $response = [];
    $response['id'] = $term->tid->value;
    $response['content_type'] = $term->vid[0]->target_id;
    $response['title'] = $term->name->value;
    $response['description'] = $term->description[0];
    $response['summary'] = $term->field_content_summary ? $term->field_content_summary->value : '';
    $response['image'] = $term->field_featured_image[0];
    $response['video'] = $term->field_featured_video[0];
    $response['audio'] = $term->field_featured_audio[0];
    $response['programme_code'] = $term->field_feature_programme_code ? $term->field_feature_programme_code->value : '';

    if ($term->hasField('field_prison_categories')) {
      $prisonCategories = [];

      foreach($term->get('field_prison_categories') as $prisonCategory) {
        array_push($prisonCategories, $prisonCategory->target_id);
      }

      $response['prison_categories'] = $prisonCategories;
    }

    if ($term->hasField('field_promoted_to_prison')) {
      $prisons = [];

      foreach($term->get('field_promoted_to_prison') as $prison) {
        array_push($prisons, $prison->target_id);
      }

      $response['prisons'] = $prisons;
    }

    return $response;
  }
}
