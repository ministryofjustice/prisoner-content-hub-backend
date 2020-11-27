<?php

/**
 * @file
 * Contains Drupal\moj_resources\Plugin\rest\resource\TermResource.
 */

namespace Drupal\moj_resources\Plugin\rest\resource;

use Psr\Log\LoggerInterface;
use Drupal\rest\ResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\moj_resources\TermApiClass;
use Symfony\Component\HttpFoundation\Request;
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
 *          name="_prison",
 *          in="query",
 *          required=true,
 *          type="integer",
 *          description="The ID of the prison to return content for",
 *      ),
 *      @SWG\Parameter(
 *          name="{term}",
 *          in="query",
 *          required=true,
 *          type="integer",
 *          description="ID of term to return",
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

class TermResource extends ResourceBase {
    /**
     * TermApiClass object
     *
     * @var TermApiClass
    */
    protected $termApiClass;

    /**
     * Current request object
     *
     * @var Request
    */
    protected $currentRequest;

    /**
     * Term id
     *
     * @var int
    */
    protected $termId;

    /**
     * Prison id
     *
     * @var int
    */
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
        $this->termId = $currentRequest->get('tid');
        $this->prisonId = $this->getPrisonId();

        self::checkTermIdIsNumeric();
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
            $container->get('request_stack')->getCurrentRequest()
        );
    }

    public function get() {
        $content = $this->termApiClass->termApiEndpoint($this->termId, $this->prisonId);
        if (!empty($content)) {
            $response = new ResourceResponse($content);
            $response->addCacheableDependency($content);
            return $response;
        }
        throw new NotFoundHttpException(t('No term found'));
    }

    protected function getPrisonId() {
      $prisonId = $this->currentRequest->get('_prison');
      return is_null($prisonId) ? 0 : $prisonId;
    }

    protected function checkTermIdIsNumeric() {
        if (is_numeric($this->termId)) {
            return true;
        }
        throw new NotFoundHttpException(
            t('The Term ID must be a numeric'),
            null,
            404
        );
    }

    protected function checkPrisonIdIsNumeric() {
      if (is_numeric($this->prisonId)) {
        return true;
      }
      throw new NotFoundHttpException(
        t('The Prison ID must be numeric'),
        null,
        404
      );
    }
}
