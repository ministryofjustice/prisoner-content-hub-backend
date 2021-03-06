<?php

/**
 * @file
 * Contains Drupal\\moj_resources\Plugin\rest\resource\SuggestedContentResource.
 */

namespace Drupal\moj_resources\Plugin\rest\resource;

use Psr\Log\LoggerInterface;
use Drupal\rest\ResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\Core\Language\LanguageManager;
use Symfony\Component\HttpFoundation\Request;
use Drupal\moj_resources\SuggestedContentApiClass;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @SWG\Get(
 *     path="/api/content/suggestions",
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
 *          description="ID of category to return, the default is to bring back all categories.",
 *      ),
 *      @SWG\Parameter(
 *          name="_number",
 *          in="query",
 *          required=false,
 *          type="integer",
 *          description="Number of results to bring back, the default is '4'.",
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
 * Provides a Suggested Content Resource
 *
 * @RestResource(
 *   id = "suggested_content_resource",
 *   label = @Translation("Suggested Content resource"),
 *   uri_paths = {
 *     "canonical" = "/v1/api/content/suggestions"
 *   }
 * )
 */

class SuggestedContentResource extends ResourceBase
{
    protected $suggestedContentApiClass;

    protected $currentRequest;

    protected $availableLanguages;

    protected $languageManager;

    protected $categoryId;

    protected $numberOfResults;

    protected $language;

    protected $prisonId;

    public function __construct(
        array $configuration,
        $pluginId,
        $pluginDefinition,
        array $serializerFormats,
        LoggerInterface $logger,
        SuggestedContentApiClass $suggestedContentApiClass,
        Request $currentRequest,
        LanguageManager $languageManager
    ) {
        $this->suggestedContentApiClass = $suggestedContentApiClass;
        $this->currentRequest = $currentRequest;
        $this->languageManager = $languageManager;
        $this->availableLanguages = $this->languageManager->getLanguages();
        $this->categoryId = self::setCategoryId();
        $this->language = self::setLanguage();
        $this->numberOfResults = self::setNumberOfResults();
        $this->prisonId = self::setPrisonId();
        self::checkLanguageParameterIsValid();
        self::checkCategoryIsNumeric();
        self::checkNumberOfResultsIsNumeric();
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
            $container->get('moj_resources.suggested_content_api_class'),
            $container->get('request_stack')->getCurrentRequest(),
            $container->get('language_manager')
        );
    }

    public function get()
    {
        $suggestedContent = $this->suggestedContentApiClass->SuggestedContentApiEndpoint(
            $this->language,
            $this->categoryId,
            $this->numberOfResults,
            $this->prisonId
        );

        if (empty($suggestedContent)) {
          throw new NotFoundHttpException(t('No suggested content found'));
        }

        $response = new ResourceResponse($suggestedContent);
        $response->addCacheableDependency($suggestedContent);
        return $response;
    }

    protected function checkLanguageParameterIsValid()
    {
        foreach($this->availableLanguages as $language)
        {
            if ($language->getid() === $this->language) {
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
        if (is_numeric($this->categoryId)) {
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

    protected function setCategoryId()
    {
        return is_null($this->currentRequest->get('_category')) ? 0 : $this->currentRequest->get('_category');
    }

    protected function setNumberOfResults()
    {
        return is_null($this->currentRequest->get('_number')) ? 4 : $this->currentRequest->get('_number');
    }

    protected function setPrisonId()
    {
        return is_null($this->currentRequest->get('_prison')) ? 0 : $this->currentRequest->get('_prison');
    }
}
