<?php

/**
 * @file
 * Contains Drupal\\moj_resources\Plugin\rest\resource\CategoryFeaturedContentResource.
 */

namespace Drupal\moj_resources\Plugin\rest\resource;

use Psr\Log\LoggerInterface;
use Drupal\rest\ResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Symfony\Component\HttpFoundation\Request;
use Drupal\moj_resources\CategoryFeaturedContentApiClass;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @SWG\Get(
 *     path="/api/category/featured",
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
 *          description="Number of results to bring back, the default is '1'.",
 *      ),
 *      @SWG\Parameter(
 *          name="_category",
 *          in="query",
 *          required=false,
 *          type="integer",
 *          description="ID of category to return, the default is to being back all categories.",
 *      ),
 *      @SWG\Parameter(
 *          name="_prison",
 *          in="query",
 *          required=false,
 *          type="integer",
 *          description="ID of category to return, the default is to being back all categories.",
 *      ),
 *
 *     @SWG\Response(response="200", description="Hub featured content resource")
 * )
 */

/**
 * Provides a Category Featured Content Resource
 *
 * @RestResource(
 *   id = "category_featured_content_resource",
 *   label = @Translation("Category Featured Content resource"),
 *   uri_paths = {
 *     "canonical" = "/v1/api/category/featured"
 *   }
 * )
 */

class CategoryFeaturedContentResource extends ResourceBase
{
    protected $featuredContentApiController;

    protected $currentRequest;

    protected $categoryId;

    protected $prisonId;

    Protected $numberOfResults;

    public function __construct(
        array $configuration,
        $pluginId,
        $pluginDefinition,
        array $serializerFormats,
        LoggerInterface $logger,
        CategoryFeaturedContentApiClass $CategoryFeaturedContentApiClass,
        Request $currentRequest
    ) {
        $this->CategoryFeaturedContentApiClass = $CategoryFeaturedContentApiClass;
        $this->currentRequest = $currentRequest;
        $this->categoryId = self::setCategory();
        $this->prisonId = self::setPrison();
        $this->numberOfResults = self::setNumberOfResults();
        self::checkNumberOfResultsIsNumeric();
        self::checkCategoryIsNumeric();
        self::checkPrisonIsNumeric();
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
            $container->get('moj_resources.category_featured_content_api_class'),
            $container->get('request_stack')->getCurrentRequest()
        );
    }

    public function get()
    {
        $featuredContent = $this->CategoryFeaturedContentApiClass->CategoryFeaturedContentApiEndpoint(
            $this->categoryId,
            $this->numberOfResults,
            $this->prisonId
        );
        if (!empty($featuredContent)) {
            $response = new ResourceResponse($featuredContent);
            $response->addCacheableDependency($featuredContent);
            return $response;
        }
        throw new NotFoundHttpException(t('No featured content found'));
    }

    protected function checkCategoryIsNumeric()
    {
        if (is_numeric($this->categoryId)) {
            return true;
        }
        throw new NotFoundHttpException(
            t('The category parameter must be a numeric'),
            null,
            404
        );
    }

    protected function checkPrisonIsNumeric()
    {
        if (is_numeric($this->prisonId)) {
            return true;
        }
        throw new NotFoundHttpException(
            t('The prison parameter must be a numeric'),
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
            t('The number of results parameter must be a numeric'),
            null,
            404
        );
    }

    protected function setNumberOfResults()
    {
        return is_null($this->currentRequest->get('_number')) ? 1 : $this->currentRequest->get('_number');
    }

    protected function setCategory()
    {
        return is_null($this->currentRequest->get('_category')) ? 0 : $this->currentRequest->get('_category');
    }

    protected function setPrison()
    {
        return is_null($this->currentRequest->get('_prison')) ? 0 : $this->currentRequest->get('_prison');
    }
}
