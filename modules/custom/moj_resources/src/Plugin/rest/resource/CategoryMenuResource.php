<?php

/**
 * @file
 * Contains Drupal\\moj_resources\Plugin\rest\resource\CategoryMenuResource.
 */

namespace Drupal\moj_resources\Plugin\rest\resource;

use Psr\Log\LoggerInterface;
use Drupal\rest\ResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\Core\Language\LanguageManager;
use Symfony\Component\HttpFoundation\Request;
use Drupal\moj_resources\categoryMenuApiClass;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @SWG\Get(
 *     path="/api/category-menu",
 *     tags={"Content"},
 *     @SWG\Parameter(
 *          name="_format",
 *          in="query",
 *          required=true,
 *          type="string",
 *          description="Response format, should be 'json'",
 *      ),
 *      @SWG\Parameter(
 *          name="_category",
 *          in="query",
 *          required=false,
 *          type="integer",
 *          description="ID of category to return, the default is to being back all categories.",
 *      ),
 *      @SWG\Parameter(
 *          name="_number",
 *          in="query",
 *          required=false,
 *          type="integer",
 *          description="Number of results to bring back, the default is '8'.",
 *      ),
 *      @SWG\Parameter(
 *          name="_offset",
 *          in="query",
 *          required=false,
 *          type="integer",
 *          description="Number of results to offset by '0'.",
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
 * Provides a Category Menu Resource
 *
 * @RestResource(
 *   id = "category_menu_resource",
 *   label = @Translation("Category Menu resource"),
 *   uri_paths = {
 *     "canonical" = "/v1/api/category-menu"
 *   }
 * )
 */

class CategoryMenuResource extends ResourceBase
{
  protected $categoryMenuApiClass;

  protected $currentRequest;

  protected $availableLanguages;

  protected $languageManager;

  protected $categoryId;

  protected $languageId;

  protected $prisonId;

  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    array $serializerFormats,
    LoggerInterface $logger,
    CategoryMenuApiClass $categoryMenuApiClass,
    Request $currentRequest,
    LanguageManager $languageManager
  ) {
    $this->categoryMenuApiClass = $categoryMenuApiClass;
    $this->currentRequest = $currentRequest;
    $this->languageManager = $languageManager;
    $this->availableLanguages = $this->languageManager->getLanguages();
    $this->categoryId = self::setCategoryId();
    $this->languageId = self::setLanguageId();
    $this->prisonId = self::setPrisonId();
    self::checkLanguageIdIsValid();
    self::checkCategoryIdIsNumeric();
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
      $container->get('moj_resources.category_menu_api_class'),
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('language_manager')
    );
  }

  public function get()
  {
    $categoryMenu = $this->categoryMenuApiClass->CategoryMenuApiEndpoint(
      $this->languageId,
      $this->categoryId,
      $this->prisonId
    );

    if (!empty($categoryMenu)) {
      $response = new ResourceResponse($categoryMenu);
      $response->addCacheableDependency($categoryMenu);
      return $response;
    }
    throw new NotFoundHttpException(t('No related content found'));
  }

  protected function checkLanguageIdIsValid()
  {
    foreach ($this->availableLanguages as $language) {
      if ($language->getid() === $this->languageId) {
        return true;
      }
    }
    throw new NotFoundHttpException(
      t('The language tag is invalid or a translation for this tag is not available'),
      null,
      404
    );
  }

  protected function checkCategoryIdIsNumeric()
  {
    if (is_numeric($this->categoryId)) {
      return true;
    }
    throw new NotFoundHttpException(
      t('The category id must be a numeric'),
      null,
      404
    );
  }

  protected function setLanguageId()
  {
    return is_null($this->currentRequest->get('_lang')) ? 'en' : $this->currentRequest->get('_lang');
  }

  protected function setCategoryId()
  {
    return is_null($this->currentRequest->get('_category')) ? 0 : $this->currentRequest->get('_category');
  }

  protected function setPrisonId()
  {
    return is_null($this->currentRequest->get('_prison')) ? 0 : $this->currentRequest->get('_prison');
  }
}
