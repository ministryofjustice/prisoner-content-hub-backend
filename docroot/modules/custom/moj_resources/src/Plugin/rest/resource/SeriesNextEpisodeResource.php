<?php

/**
 * @file
 * Contains Drupal\\moj_resources\Plugin\rest\resource\SeriesNextEpisodeResource.
 */

namespace Drupal\moj_resources\Plugin\rest\resource;

use Psr\Log\LoggerInterface;
use Drupal\rest\ResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\Core\Language\LanguageManager;
use Symfony\Component\HttpFoundation\Request;
use Drupal\moj_resources\SeriesContentApiClass;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @SWG\Get(
 *     path="/api/content/series/{id}/next",
 *     tags={"Content"},
 *     @SWG\Parameter(
 *          name="_format",
 *          in="query",
 *          required=true,
 *          type="string",
 *          description="Response format, should be 'json'",
 *      ),
 *      @SWG\Parameter(
 *          name="_number",
 *          in="query",
 *          required=false,
 *          type="integer",
 *          description="Number of results to bring back, the default is all",
 *      ),
 *      @SWG\Parameter(
 *          name="id",
 *          in="path",
 *          required=false,
 *          type="integer",
 *          description="Term ID of category to return, the default is to being back all categories.",
 *      ),
 *      @SWG\Parameter(
 *          name="_lang",
 *          in="query",
 *          required=false,
 *          type="string",
 *          description="The language tag to translate results, if there is no translation available then the site default is returned, the default is 'en' (English). Options are 'en' (English) or 'cy' (Welsh).",
 *      ),
 *
 *     @SWG\Response(response="200", description="Hub featured content resource")
 * )
 */

/**
 * Provides a Series Next Episode Resource
 *
 * @RestResource(
 *   id = "series_next_episode_resource",
 *   label = @Translation("Series Next Episode resource"),
 *   uri_paths = {
 *     "canonical" = "/v1/api/content/series/{id}/next"
 *   }
 * )
 */

class SeriesNextEpisodeResource extends ResourceBase
{
  protected $seriesContentApiController;

  protected $currentRequest;

  protected $availableLangs;

  protected $languageManager;

  protected $paramater_category;

  protected $parameter_language_tag;

  protected $parameter_number_results;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    SeriesContentApiClass $SeriesContentApiClass,
    Request $currentRequest,
    LanguageManager $languageManager
  ) {
    $this->seriesContentApiClass = $SeriesContentApiClass;
    $this->currentRequest = $currentRequest;
    $this->languageManager = $languageManager;
    $this->availableLangs = $this->languageManager->getLanguages();    
    $this->parameter_number_results = self::setNumberOfResults();
    $this->parameter_language_tag = self::setLanguage();
    $this->parameter_episode_id = self::setEpisodeId();
    $this->parameter_prison = self::setPrison();
    $this->parameter_sort_order = self::setSortOrder();

    self::checkLanguageParameterIsValid();
    self::checkNumberOfResultsIsNumeric();    
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
  }

  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('moj_resources.series_content_api_class'),
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('language_manager')
    );
  }

  public function get()
  {
    $seriesContent = $this->seriesContentApiClass->SeriesNextEpisodeApiEndpoint(
      $this->parameter_language_tag,
      $this->currentRequest->get('id'),
      $this->parameter_number_results,
      $this->parameter_episode_id,
      $this->parameter_prison,
      $this->parameter_sort_order
    );

    $response = new ResourceResponse($seriesContent);
    $response->addCacheableDependency($seriesContent);
    return $response;
  }

  protected function checkLanguageParameterIsValid()
  {
    foreach ($this->availableLangs as $lang) {
      if ($lang->getid() === $this->parameter_language_tag) {
        return true;
      }
    }
    throw new NotFoundHttpException(
      t('The language tag invalid or translation for this tag is not avilable'),
      null,
      404
    );
  }

  protected function checkCategoryIsNumeric()
  {
    if (is_numeric($this->paramater_category)) {
      return true;
    }
    throw new NotFoundHttpException(
      t('The category parameter must be a numeric'),
      null,
      404
    );
  }

  protected function checkNumberOfResultsIsNumeric()
  {
    if (is_numeric($this->parameter_number_results)) {
      return true;
    }
    throw new NotFoundHttpException(
      t('The number of results parameter must be a numeric'),
      null,
      404
    );
  }

  protected function setLanguage()
  {
    return is_null($this->currentRequest->get('_lang')) ? 'en' : $this->currentRequest->get('_lang');
  }


  protected function setNumberOfResults()
  {
    return is_null($this->currentRequest->get('_number')) ? 0 : $this->currentRequest->get('_number');
  }

  protected function setEpisodeId()
  {
    return is_null($this->currentRequest->get('_episode_id')) ? 0 : $this->currentRequest->get('_episode_id');
  }

  protected function setPrison()
  {
    return is_null($this->currentRequest->get('_prison')) ? 0 : $this->currentRequest->get('_prison');
  }

  protected function setSortOrder()
  {
    return is_null($this->currentRequest->get('_sort_order')) ? 'DESC' : $this->currentRequest->get('_sort_order');
  }
}
