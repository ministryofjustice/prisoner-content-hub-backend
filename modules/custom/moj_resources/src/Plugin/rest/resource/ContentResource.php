<?php

/**
 * @file
 * Contains Drupal\\moj_resources\Plugin\rest\resource\ContentResource.
 */

namespace Drupal\moj_resources\Plugin\rest\resource;

use Psr\Log\LoggerInterface;
use Drupal\rest\ResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\Core\Language\LanguageManager;
use Drupal\moj_resources\ContentApiClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

 /**
 * @SWG\Get(
 *     path="/api/content/{contentId}",
 *     tags={"Content"},
 *     @SWG\Parameter(
 *          name="{contentId}",
 *          in="path",
 *          required=true,
 *          type="integer",
 *          description="ID of content to be returned",
 *      ),
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
 *          description="ID of prison content to belong to to return, the default is belonging to all prisons.",
 *      ),
 *      @SWG\Parameter(
 *          name="_lang",
 *          in="query",
 *          required=false,
 *          type="string",
 *          description="The language tag to translate results, if there is no translation available then the site default is returned, the default is 'en' (English). Options are 'en' (English) or 'cy' (Welsh).",
 *      ),
 *
 *     @SWG\Response(response="200", description="Hub content resource")
 * )
 */

/**
 * Provides a Content Resource
 *
 * @RestResource(
 *   id = "content_resource",
 *   label = @Translation("Content resource"),
 *   uri_paths = {
 *     "canonical" = "/v1/api/content/{contentId}"
 *   }
 * )
 */

class ContentResource extends ResourceBase
{
    protected $contentApiController;

    protected $contentApiClass;

    protected $currentRequest;

    protected $availableLanguages;

    protected $languageManager;

    protected $prisonId;

    protected $contentId;

    Protected $languageId;

    public function __construct(
        array $configuration,
        $pluginId,
        $pluginDefinition,
        array $serializerFormats,
        LoggerInterface $logger,
        ContentApiClass $contentApiClass,
        Request $currentRequest,
        LanguageManager $languageManager
    ) {
        $this->contentApiClass = $contentApiClass;
        $this->currentRequest = $currentRequest;
        $this->languageManager = $languageManager;
        $this->availableLanguages = $this->languageManager->getLanguages();
        $this->prisonId = self::setPrisonId();
        $this->contentId = $this->currentRequest->get('nid');
        $this->languageId =self::setLanguageId();
        self::checkLanguageIdIsValid();
        self::checkPrisonIdIsNumeric();
        self::checkContentIdIsNumeric();
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
            $container->get('moj_resources.content_api_class'),
            $container->get('request_stack')->getCurrentRequest(),
            $container->get('language_manager')
        );
    }

    public function get()
    {
        $content = $this->contentApiClass->ContentApiEndpoint($this->languageId, $this->contentId, $this->prisonId);
        if (!empty($content)) {
            $response = new ResourceResponse($content);
            $response->addCacheableDependency($content);
            return $response;
        }
        throw new NotFoundHttpException(t('No content found'));
    }

    protected function setLanguageId()
    {
        return is_null($this->currentRequest->get('_lang')) ? 'en' : $this->currentRequest->get('_lang');
    }

    protected function setPrisonId()
    {
        return is_null($this->currentRequest->get('_prison')) ? 0 : intval($this->currentRequest->get('_prison'));
    }

    protected function checkLanguageIdIsValid()
    {
        foreach($this->availableLanguages as $language)
        {
            if ($language->getid() === $this->languageId) {
                return true;
            }
        }
        throw new NotFoundHttpException(
            t('The language id is invalid or a translation for this content is not available'),
            null,
            400
        );
    }

    protected function checkContentIdIsNumeric()
    {
        if (is_numeric($this->contentId)) {
            return true;
        }
        throw new NotFoundHttpException(
            t('The content ID must be numeric'),
            null,
            400
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
}


