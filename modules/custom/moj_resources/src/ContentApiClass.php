<?php

namespace Drupal\moj_resources;

use Drupal\node\NodeInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\moj_resources\Utilities;

/**
 * ContentApiClass
 */

class ContentApiClass
{
  /**
   * Language Tag
   *
   * @var string
   */
  protected $languageId;
  /**
   * Prison Id
   *
   * @var integer
   */
  protected $prisonId;
  /**
   * Node_storage object
   *
   * @var Drupal\Core\Entity\EntityManagerInterface
   */
  protected $nodeStorage;

  /**
   * TermStorage object
   *
   * @var EntityManagerInterface
  */
  protected $termStorage;

  public function __construct(
    EntityTypeManagerInterface $entityTypeManager
  ) {
    $this->nodeStorage = $entityTypeManager->getStorage('node');
    $this->termStorage = $entityTypeManager->getStorage('taxonomy_term');
  }
  /**
   * API resource function
   *
   * @param [string] $languageId
   * @return array
   */
  public function ContentApiEndpoint($languageId, $contentId, $prisonId = 0)
  {
    $this->languageId = $languageId;
    $this->prisonId = $prisonId;
    $prison  = Utilities::getPrison($prisonId);
    $content = $this->nodeStorage->load($contentId);

    if (is_null($content)) {
      throw new NotFoundHttpException(
        'No matching content found',
        null,
        404
      );
    }

    $prisonCategories = Utilities::getPrisonCategoriesFor($prison);
    $contentPrisonCategories = Utilities::getPrisonCategoriesFor($content);
    $contentPrisons = Utilities::getPrisonsFor($content);

    if (count($contentPrisons) === 0) {
      $matchingPrisonCategories = array_intersect($prisonCategories, $contentPrisonCategories);
      if (empty($matchingPrisonCategories)) {
        throw new BadRequestHttpException(
          'The content does not have a matching prison category for this prison',
          null,
          400
        );
      }
    } else {
      $matchingPrisons = in_array($prisonId, $contentPrisons);
      $hasNoMatchingPrisonCategories = empty($matchingPrisons);
    }

    $translatedContent = $this->translateNode($content);

    return $this->createReturnObject($translatedContent);
  }
  /**
   * TranslateNode function
   *
   * @param NodeInterface $content
   *
   * @return $content
   */
  private function translateNode(NodeInterface $content)
  {
    return $content->hasTranslation($this->languageId) ? $content->getTranslation($this->languageId) : $content;
  }

  /**
   *
   */
  private function createReturnObject($content)
  {
    $contentType = $content->type->target_id;

    if (($this->prisonId != 0) && (count($content->field_moj_prisons) > 0)) {
      $found = false;

      foreach ($content->field_moj_prisons as $key => $n) {
        if ($this->prisonId == $n->target_id) {
          $found = true;
          break;
        }
      }

      if (!$found) {
        return [];
      }
    }

    $response = $this->createItemResponse($content);

    switch ($contentType) {
      case 'moj_radio_item':
        return array_merge($response, $this->createAudioItemResponse($content));
      case 'moj_video_item':
        return array_merge($response, $this->createVideoItemResponse($content));
      case 'moj_pdf_item':
        return array_merge($response, $this->createPDFItemResponse($content));
      case 'page':
        return array_merge($response, $this->createPageItemResponse($content));
      case 'landing_page':
        return array_merge($response, $this->createLandingPageItemResponse($content));

      default:
        return $response;
    }
  }

  private function createItemResponse($content)
  {
    $response = [];
    $response["content_type"] =  $content->type->target_id;
    $response["title"] =  $content->title->value;
    $response["id"] =  $content->nid->value;
    $response["image"] =  $content->field_moj_thumbnail_image[0];
    $response["description"] =  $content->field_moj_description[0];
    $response["categories"] =  $content->field_moj_top_level_categories;
    if ($content->field_moj_secondary_tags) {
      $response["secondary_tags"] =  $content->field_moj_secondary_tags;
    } else {
      $response["secondary_tags"] =  $content->field_moj_tags;
    }
    $response["prisons"] =  $content->field_moj_prisons;

    return  $response;
  }

  private function createAudioItemResponse($content)
  {
    $response = [];

    $response['media'] = $content->field_moj_audio[0];
    $response["episode_id"] = $this->createEpisodeId($content);
    $response["series_id"] = $content->field_moj_series[0]->target_id;
    $response["season"] = $content->field_moj_season->value;
    $response["episode"] = $content->field_moj_episode->value;
    $response["duration"] = $content->field_moj_duration->value;
    $response["programme_code"] = $content->field_moj_programme_code->value;

    return $response;
  }

  private function createVideoItemResponse($content)
  {
    $response = [];
    $response['media'] = $content->field_video[0];
    $response["episode_id"] = $this->createEpisodeId($content);
    $response["series_id"] = $content->field_moj_series[0]->target_id;
    $response["season"] = $content->field_moj_season->value;
    $response["episode"] = $content->field_moj_episode->value;
    $response["duration"] = $content->field_moj_duration->value;

    return $response;
  }

  private function createPageItemResponse($content)
  {
    $response = [];
    $response['stand_first'] = $content->field_moj_stand_first->value;
    return $response;
  }


  private function createPDFItemResponse($content)
  {
    $response = [];
    $response['media'] = $content->field_moj_pdf[0];

    return $response;
  }

  private function createEpisodeId($content)
  {
    return ($content->field_moj_season->value * 1000) + ($content->field_moj_episode->value);
  }

  private function createLandingPageItemResponse($content)
  {
    $response = [];
    $response['featured_content_id'] =  $content->field_moj_landing_feature_contet[0]->target_id;
    $response['category_id'] =  $content->field_moj_landing_page_term[0]->target_id;
    return  $response;
  }
}
