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
   * Content Id
   *
   * @var integer
   */
  protected $contentId;
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
   * @param string $languageId
   * @param string $contentId
   * @param string $prisonId
   * @return array
   */
  public function ContentApiEndpoint($languageId, $contentId, $prisonId = 0)
  {
    $this->languageId = $languageId;
    $this->prisonId = $prisonId;
    $this->contentId = $contentId;

    $content = $this->getMatchingContent();

    return $this->createReturnObject($translatedContent);
  }

  /**
   * Validate the content is OK for prison/prison categories
   *
   * @return NodeInterface
   */
  private function getMatchingContent() {
    $prison = Utilities::getTermFor($this->prisonId, $this->termStorage);
    $content = Utilities::getNodeFor($this->contentId, $this->nodeStorage);
    $contentPrisons = Utilities::getPrisonsFor($content);

    if (count($contentPrisons) === 0) {
      $prisonCategories = Utilities::getPrisonCategoriesFor($prison);
      $contentPrisonCategories = Utilities::getPrisonCategoriesFor($content);
      $matchingPrisonCategories = array_intersect($prisonCategories, $contentPrisonCategories);

      if (empty($matchingPrisonCategories)) {
        throw new BadRequestHttpException(
          'The content does not have a matching prison category for this prison',
          null,
          400
        );
      }
    } else {
      $matchingPrisons = in_array($this->prisonId, $contentPrisons);

      if (!$matchingPrisons) {
        throw new BadRequestHttpException(
          'The content is not available for this prison',
          null,
          400
        );
      }
    }

    $translatedContent = $this->translateNode($content);

    return $translatedContent;
  }
  /**
   * TranslateNode function
   *
   * @param NodeInterface $content
   *
   * @return NodeInterface
   */
  private function translateNode(NodeInterface $content)
  {
    return $content->hasTranslation($this->languageId) ? $content->getTranslation($this->languageId) : $content;
  }

  /**
   * Return the content data
   *
   * @param NodeInterface $content
   *
   * @return array
   */
  private function createReturnObject($content)
  {
    $contentType = $content->type->target_id;

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

  /**
   * Return the default content data
   *
   * @param NodeInterface $content
   *
   * @return array
   */
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

  /**
   * Return the audio item specific content data
   *
   * @param NodeInterface $content
   *
   * @return array
   */
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

  /**
   * Return the video item specific content data
   *
   * @param NodeInterface $content
   *
   * @return array
   */
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

  /**
   * Return the page item specific content data
   *
   * @param NodeInterface $content
   *
   * @return array
   */
  private function createPageItemResponse($content)
  {
    $response = [];
    $response['stand_first'] = $content->field_moj_stand_first->value;
    return $response;
  }

  /**
   * Return the PDF item specific content data
   *
   * @param NodeInterface $content
   *
   * @return array
   */
  private function createPDFItemResponse($content)
  {
    $response = [];
    $response['media'] = $content->field_moj_pdf[0];

    return $response;
  }

  /**
   * Return the episode if for a piece of content
   *
   * @param NodeInterface $content
   *
   * @return array
   */
  private function createEpisodeId($content)
  {
    return ($content->field_moj_season->value * 1000) + ($content->field_moj_episode->value);
  }

  /**
   * Return the landing page item specific content data
   *
   * @param NodeInterface $content
   *
   * @return array
   */
  private function createLandingPageItemResponse($content)
  {
    $response = [];

    // IMPORTANT: DO NOT change the incorrectly spelt field name, it is incorrect in Drupal
    $response['featured_content_id'] =  $content->field_moj_landing_feature_contet[0]->target_id;
    $response['category_id'] =  $content->field_moj_landing_page_term[0]->target_id;
    return  $response;
  }
}
