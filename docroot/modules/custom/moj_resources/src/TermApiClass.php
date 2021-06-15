<?php

namespace Drupal\moj_resources;

use Drupal\node\NodeInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Symfony\Component\Serializer\Serializer;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * PromotedContentApiClass
 */

class TermApiClass
{
  /**
   * Term
   *
   * @var array
   */
  protected $term;
  /**
   * Language Tag
   *
   * @var string
   */
  protected $lang;
  /**
   * Node_storage object
   *
   * @var Drupal\Core\Entity\EntityTypeManager
   */
  protected $node_storage;

  /**
   * Class Constructor
   *
   * @param EntityTypeManager $entityTypeManager
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager
  ) {
    $this->term_storage = $entityTypeManager->getStorage('taxonomy_term');
  }
  /**
   * API resource function
   *
   * @param [string] $lang
   * @param [string] $category
   * @return array
   */
  public function TermApiEndpoint($lang, $term_id)
  {
    $this->lang = $lang;
    $terms = $this->term_storage->load($term_id);
    return $this->decorateResponse($terms);
  }
  /**
   * Decorate term response
   *
   * @param Node $term
   * @return array
   */
  private function decorateResponse($term)
  {
    $result = [];
    $result['id'] = $term->tid->value;
    $result['content_type'] = $term->vid[0]->target_id;
    $result['title'] = $term->name->value;
    $result['description'] = $term->description[0];
    $result['summary'] = $term->field_content_summary ? $term->field_content_summary->value : '';
    $result['image'] = $term->field_featured_image[0];
    $result['video'] = ''; // Field removed, return empty string.
    $result['audio'] = $term->field_featured_audio[0];
    $result['programme_code'] = $term->field_feature_programme_code ? $term->field_feature_programme_code->value : '';

    return $result;
  }

  /**
   * TranslateNode function
   *
   * @param NodeInterface $term
   *
   * @return $term
   */
  protected function translateNode($term)
  {
    return $term->hasTranslation($this->lang) ? $term->getTranslation($this->lang) : $term;
  }
}
