<?php

namespace Drupal\moj_resources;

use Drupal\node\NodeInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * PromotedContentApiClass
 */

class TermApiClass
{

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
   * @param string $termId
   * @return array
   */
  public function TermApiEndpoint($termId)
  {
    $term = $this->termStorage->load($termId);
    return $this->createReturnObject($term);
  }
  /**
   * Decorate term response
   *
   * @param Node $term
   * @return array
   */
  private function createReturnObject($term)
  {
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

    return $response;
  }
}
