<?php

/**
 * @file
 * Contains Drupal\\moj_resources\Plugin\rest\resource\FeaturedContentResource.
 */

namespace Drupal\moj_resources\Plugin\rest\resource;

use Psr\Log\LoggerInterface;
use Drupal\rest\ResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\Core\Language\LanguageManager;
use Symfony\Component\HttpFoundation\Request;
use Drupal\moj_resources\NewFeaturedContentApiClass;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @SWG\Get(
 *     path="/v2/api/content/featured/",
 *     tags={"Content"},
 *     @SWG\Parameter(
 *          name="_format",
 *          in="query",
 *          required=true,
 *          type="string",
 *          description="Response format, should be 'json'",
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
 * Provides a Featured Content Resource
 *
 * @RestResource(
 *   id = "new_featured_content_resource",
 *   label = @Translation("Featured Content resource (v2)"),
 *   uri_paths = {
 *     "canonical" = "/v2/api/content/featured"
 *   }
 * )
 */

class NewFeaturedContentResource extends ResourceBase
{
    protected $featuredContentApiController;

    protected $currentRequest;

    protected $prisonId;

    public function __construct(
        array $configuration,
        $pluginId,
        $pluginDefinition,
        array $serializerFormats,
        LoggerInterface $logger,
        NewFeaturedContentApiClass $featuredContentApiClass,
        Request $currentRequest
    ) {
        $this->featuredContentApiClass = $featuredContentApiClass;
        $this->currentRequest = $currentRequest;
        $this->prisonId = self::setPrisonId();
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
            $container->get('moj_resources.new_featured_content_api_class'),
            $container->get('request_stack')->getCurrentRequest()
        );
    }

    public function get()
    {
        $featuredContent = $this->featuredContentApiClass->FeaturedContentApiEndpoint(
          $this->prisonId
        );
        if (!empty($featuredContent)) {
            $response = new ResourceResponse($featuredContent);
            $response->addCacheableDependency($featuredContent);
            return $response;
        }
        throw new NotFoundHttpException(t('No featured content found'));
    }

    protected function checkPrisonIdIsNumeric()
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

    protected function setPrisonId()
    {
        return is_null($this->currentRequest->get('_prison')) ? 0 : intval($this->currentRequest->get('_prison'));
    }
}
