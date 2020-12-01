<?php

/**
 * @file
 * Contains Drupal\moj_resources\Plugin\rest\resource\TermResource.
 */

namespace Drupal\moj_resources\Plugin\rest\resource;

use Psr\Log\LoggerInterface;
use Drupal\rest\ResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\Core\Language\LanguageManager;
use Drupal\moj_resources\TermApiClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

 /**
 * @SWG\Get(
 *     path="/api/term/{tid}",
 *     tags={"Category"},
 *     @SWG\Parameter(
 *          name="_format",
 *          in="query",
 *          required=true,
 *          type="string",
 *          description="Response format, should be 'json'",
 *      ),
 *      @SWG\Parameter(
 *          name="{term}",
 *          in="query",
 *          required=true,
 *          type="integer",
 *          description="ID of term to return",
 *      ),
 *      @SWG\Parameter(
 *          name="_prison",
 *          in="query",
 *          required=false,
 *          type="integer",
 *          description="ID of prison term to belong to to return, the default is belonging to all prisons.",
 *      ),
 *
 *     @SWG\Response(response="200", description="Hub term resource")
 * )
 */

/**
 * Provides a Term Resource
 *
 * @RestResource(
 *   id = "term_resource",
 *   label = @Translation("Term resource"),
 *   uri_paths = {
 *     "canonical" = "/v1/api/term/{tid}"
 *   }
 * )
 */

class TermResource extends ResourceBase
{
    protected $termApiClass;

    protected $currentRequest;

    protected $termId;

    protected $prisonId;

    public function __construct(
        array $configuration,
        $pluginId,
        $pluginDefinition,
        array $serializerFormats,
        LoggerInterface $logger,
        TermApiClass $termApiClass,
        Request $currentRequest
    ) {
        $this->termApiClass = $termApiClass;
        $this->currentRequest = $currentRequest;
        $this->prisonId = self::setPrisonId();
        $this->termId = $this->currentRequest->get('tid');
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
            $container->get('moj_resources.term_api_class'),
            $container->get('request_stack')->getCurrentRequest(),
            $container->get('language_manager')
        );
    }

    public function get()
    {
        self::checkTermIdIsNumeric();
        $term = $this->termApiClass->TermApiEndpoint($this->termId, $this->prisonId);
        if (!empty($term)) {
            $response = new ResourceResponse($term);
            $response->addCacheableDependency($term);
            return $response;
        }
        throw new NotFoundHttpException(t('No term found'));
    }

    protected function checkTermIdIsNumeric()
    {
        if (is_numeric($this->termId)) {
            return true;
        }
        throw new NotFoundHttpException(
            t('The term parameter must be a numeric'),
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
            t('The prison ID must be numeric'),
            null,
            400
        );
    }

    protected function setPrisonId()
    {
        return is_null($this->currentRequest->get('_prison')) ? 0 : intval($this->currentRequest->get('_prison'));
    }
}


