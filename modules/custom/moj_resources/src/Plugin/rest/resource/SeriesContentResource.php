<?php

/**
 * @file
 * Contains Drupal\\moj_resources\Plugin\rest\resource\SeriesContentResource.
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
 *     path="/api/content/series/{seriesId}",
 *     tags={"Content"},
 *     @SWG\Parameter(
 *          name="_format",
 *          in="query",
 *          required=true,
 *          type="string",
 *          description="Response format, should be 'json'",
 *      ),
 *     @SWG\Parameter(
 *          name="_sort_order",
 *          in="query",
 *          required=false,
 *          type="string",
 *          description="The order of the results to return, default DESC",
 *      ),
 *      @SWG\Parameter(
 *          name="_number",
 *          in="query",
 *          required=false,
 *          type="integer",
 *          description="Number of results to bring back, the default is all",
 *      ),
 *      @SWG\Parameter(
 *          name="_prison",
 *          in="query",
 *          required=true,
 *          type="integer",
 *          description="The ID of the prison to return content for",
 *      ),
 *      @SWG\Parameter(
 *          name="seriesId",
 *          in="path",
 *          required=false,
 *          type="integer",
 *          description="The ID for the Series to return content for",
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
 * Provides a Series Content Resource
 *
 * @RestResource(
 *   id = "series_content_resource",
 *   label = @Translation("Series Content resource"),
 *   uri_paths = {
 *     "canonical" = "/v1/api/content/series/{seriesId}"
 *   }
 * )
 */

class SeriesContentResource extends ResourceBase
{
  protected $seriesContentApiClass;

  protected $currentRequest;

  protected $availableLanguages;

  protected $languageManager;

  protected $categoryId;

  protected $language;

  protected $numberOfResults;

  protected $resultsOffset;

  protected $prisonId;

  protected $sortOrder;


  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    array $serializerFormats,
    LoggerInterface $logger,
    SeriesContentApiClass $SeriesContentApiClass,
    Request $currentRequest,
    LanguageManager $languageManager
  ) {
    $this->seriesContentApiClass = $SeriesContentApiClass;
    $this->currentRequest = $currentRequest;
    $this->languageManager = $languageManager;
    $this->availableLanguages = $this->languageManager->getLanguages();
    $this->numberOfResults = self::setNumberOfResults();
    $this->language = self::setLanguage();
    $this->resultsOffset = self::setOffsetOfResults();
    $this->prisonId = self::setPrisonId();
    $this->sortOrder = self::setSortOrder();


    self::checkLanguageParameterIsValid();
    self::checkNumberOfResultsIsNumeric();
    self::checkPrisonIdIsNumeric();
    parent::__construct($configuration, $pluginId, $pluginDefinition, $serializerFormats, $logger);
  }

  public static function create(
    ContainerInterface $container,
    array $configuration,
    $pluginId,
    $pluginDefinition
  ) {
    return new static(
      $configuration,
      $pluginId,
      $pluginDefinition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('moj_resources.series_content_api_class'),
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('language_manager')
    );
  }

  public function get()
  {
    $contentForSeries = $this->seriesContentApiClass->SeriesContentApiEndpoint(
      $this->language,
      $this->currentRequest->get('seriesId'),
      $this->numberOfResults,
      $this->resultsOffset,
      $this->prisonId,
      $this->sortOrder
    );
    if (!empty($contentForSeries)) {
      $response = new ResourceResponse($contentForSeries);
      $response->addCacheableDependency($contentForSeries);
      return $response;
    }
    throw new NotFoundHttpException(t('No series content found'));
  }

  protected function checkLanguageParameterIsValid()
  {
    foreach ($this->availableLanguages as $language) {
      if ($language->getid() === $this->language) {
        return true;
      }
    }
    throw new NotFoundHttpException(
      t('The language tag invalid or translation for this tag is not available'),
      null,
      404
    );
  }

  protected function checkPrisonIdIsNumeric()
  {
    if (is_numeric($this->prisonId)) {
      return true;
    }
    throw new NotFoundHttpException(
      t('The category parameter must be numeric'),
      null,
      404
    );
  }

  protected function checkNumberOfResultsIsNumeric()
  {
    if (is_numeric($this->numberOfResults)) {
      return true;
    }
    throw new NotFoundHttpException(
      t('The number of results parameter must be numeric'),
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

  protected function setOffsetOfResults()
  {
    return is_null($this->currentRequest->get('_offset')) ? 0 : $this->currentRequest->get('_offset');
  }

  protected function setPrisonId()
  {
    return is_null($this->currentRequest->get('_prison')) ? 0 : $this->currentRequest->get('_prison');
  }

  protected function setSortOrder()
  {
    return is_null($this->currentRequest->get('_sort_order')) ? 'DESC' : $this->currentRequest->get('_sort_order');
  }
}
