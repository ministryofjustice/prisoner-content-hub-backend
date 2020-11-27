<?php

/**
 * @file
 * Contains Drupal\\moj_resources\Plugin\rest\resource\VocabularyResource.
 */

namespace Drupal\moj_resources\Plugin\rest\resource;

use Psr\Log\LoggerInterface;
use Drupal\rest\ResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\Core\Language\LanguageManager;
use Drupal\moj_resources\VocabularyApiClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

 /**
 * @SWG\Get(
 *     path="/api/vocabulary/{category}",
 *     tags={"Category"},
 *     @SWG\Parameter(
 *          name="_format",
 *          in="query",
 *          required=true,
 *          type="string",
 *          description="Response format, should be 'json'",
 *      ),
 *      @SWG\Parameter(
 *          name="{category}",
 *          in="query",
 *          required=true,
 *          type="integer",
 *          description="ID of category to return",
 *      ),
 *      @SWG\Parameter(
 *          name="_lang",
 *          in="query",
 *          required=false,
 *          type="string",
 *          description="The language tag to translate results, if there is no translation available then the site default is returned, the default is 'en' (English). Options are 'en' (English) or 'cy' (Welsh).",
 *      ),
 *
 *     @SWG\Response(response="200", description="Hub vocabulary resource")
 * )
 */

/**
 * Provides a Vocabulary Resource
 *
 * @RestResource(
 *   id = "vocabulary_resource",
 *   label = @Translation("Vocabulary resource"),
 *   uri_paths = {
 *     "canonical" = "/v1/api/vocabulary/{category}"
 *   }
 * )
 */

class VocabularyResource extends ResourceBase
{
    protected $vocabularyApiClass;

    protected $currentRequest;

    protected $availableLanguages;

    protected $languageManager;

    protected $taxonomyName;

    Protected $languageId;

    public function __construct(
        array $configuration,
        $pluginId,
        $pluginDefinition,
        array $serializerFormats,
        LoggerInterface $logger,
        VocabularyApiClass $vocabularyApiClass,
        Request $currentRequest,
        LanguageManager $languageManager
    ) {
        $this->vocabularyApiClass = $vocabularyApiClass;
        $this->currentRequest = $currentRequest;
        $this->languageManager = $languageManager;

        $this->availableLanguages = $this->languageManager->getLanguages();
        $this->languageId = self::setLanguageId();
        $this->taxonomyName = $this->currentRequest->get('category');

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
            $container->get('moj_resources.vocabulary_api_class'),
            $container->get('request_stack')->getCurrentRequest(),
            $container->get('language_manager')
        );
    }

    public function get()
    {
        self::checkTaxonomyNameIsString();
        $content = $this->vocabularyApiClass->VocabularyApiEndpoint($this->languageId, $this->taxonomyName);

        if (!empty($content)) {
            $response = new ResourceResponse($content);
            $response->addCacheableDependency($content);
            return $response;
        }
        throw new NotFoundHttpException(t('No featured content found'));
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
            t('The language tag invalid or translation for this tag is not avilable'),
            null,
            404
        );
    }

    protected function checkTaxonomyNameIsString()
    {
        if (is_string($this->taxonomyName)) {
            return true;
        }
        throw new NotFoundHttpException(
            t('The taxonomy name must the machine name of a drupal taxonomy'),
            null,
            404
        );
    }

    protected function setLanguageId()
    {
        return is_null($this->currentRequest->get('_lang')) ? 'en' : $this->currentRequest->get('_lang');
    }
}
