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
 *     path="/api/term/{termId}",
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
 *          name="_lang",
 *          in="query",
 *          required=false,
 *          type="string",
 *          description="The language tag to translate results, if there is no translation available then the site default is returned, the default is 'en' (English). Options are 'en' (English) or 'cy' (Welsh).",
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
 *     "canonical" = "/v1/api/term/{termId}"
 *   }
 * )
 */

class TermResource extends ResourceBase
{
    protected $termApiClass;

    protected $currentRequest;

    protected $availableLanguages;

    protected $languageManager;

    protected $termId;

    protected $languageId;

    public function __construct(
        array $configuration,
        $pluginId,
        $pluginDefinition,
        array $serializerFormats,
        LoggerInterface $logger,
        TermApiClass $termApiClass,
        Request $currentRequest,
        LanguageManager $languageManager
    ) {
        $this->termApiClass = $termApiClass;
        $this->currentRequest = $currentRequest;
        $this->languageManager = $languageManager;
        $this->availableLanguages = $this->languageManager->getLanguages();
        $this->languageId = self::setLanguageId();
        $this->termId = $this->currentRequest->get('tid');

        self::checkLanguageIdIsValid();

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
        $content = $this->termApiClass->TermApiEndpoint($this->languageId, $this->termId);
        if (!empty($content)) {
            $response = new ResourceResponse($content);
            $response->addCacheableDependency($content);
            return $response;
        }
        throw new NotFoundHttpException(t('No term found'));
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
            t('The language tag is invalid or a translation for this tag is not available'),
            null,
            404
        );
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

    protected function setLanguageId()
    {
        return is_null($this->currentRequest->get('_lang')) ? 'en' : $this->currentRequest->get('_lang');
    }
}


