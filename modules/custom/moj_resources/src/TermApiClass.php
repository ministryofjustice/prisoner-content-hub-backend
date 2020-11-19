<?php

namespace Drupal\moj_resources;

use Drupal\node\NodeInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Entity\Query\QueryFactory;
use Symfony\Component\Serializer\Serializer;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * PromotedContentApiClass
 */

class TermApiClass
{
  /**
   * Language Id
   *
   * @var string
   */
  protected $languageId;
  /**
   * termStorage object
   *
   * @var Drupal\Core\Entity\EntityTypeManager
   */
  protected $termStorage;

  /**
   * The custom serializer for terms.
   *
   * @var \Symfony\Component\Serializer\Serializer
   */
  protected $termSerializer;

  /**
   * Class Constructor
   *
   * @param EntityTypeManager $entityTypeManager
   * @param QueryFactory $entityQuery
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    QueryFactory $entityQuery,
    Serializer $termSerializer
  ) {
    $this->termStorage = $entityTypeManager->getStorage('taxonomy_term');
    $this->termSerializer = $termSerializer;
  }
  /**
   * API resource function
   *
   * @param string $languageId
   * @param string $termId
   * @return array
   */
  public function TermApiEndpoint($languageId, $termId)
  {
    $this->languageId = $languageId;
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
    $content = [];
    $content['id'] = $term->tid->value;
    $content['content_type'] = $term->vid[0]->target_id;
    $content['title'] = $term->name->value;
    $content['description'] = $term->description[0];
    $content['summary'] = $term->field_content_summary ? $term->field_content_summary->value : '';
    $content['image'] = $term->field_featured_image[0];
    $content['video'] = $term->field_featured_video[0];
    $content['audio'] = $term->field_featured_audio[0];
    $content['programme_code'] = $term->field_feature_programme_code ? $term->field_feature_programme_code->value : '';

    return $content;
  }
}
