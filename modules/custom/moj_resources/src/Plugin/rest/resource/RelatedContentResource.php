<?php

/**
 * @file
 * Contains Drupal\\moj_resources\Plugin\rest\resource\RelatedContentResource.
 */

namespace Drupal\moj_resources\Plugin\rest\resource;

use Psr\Log\LoggerInterface;
use Drupal\rest\ResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\Core\Language\LanguageManager;
use Symfony\Component\HttpFoundation\Request;
use Drupal\moj_resources\RelatedContentApiClass;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @SWG\Get(
 *     path="/api/content/related",
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
 * Provides a Related Content Resource
 *
 * @RestResource(
 *   id = "related_content_resource",
 *   label = @Translation("Related Content resource"),
 *   uri_paths = {
 *     "canonical" = "/v1/api/content/related"
 *   }
 * )
 */

class RelatedContentResource extends ResourceBase
{
    protected $relatedContentApiController;

    protected $currentRequest;

    protected $availableLanguages;

    protected $languageManager;

    protected $categoryId;

    protected $numberOfResults;

    protected $offsetIntoNumberOfResults;

    protected $languageId;

    protected $prisonId;

    protected $sortOrder;

    public function __construct(
        array $configuration,
        $pluginId,
        $pluginDefinition,
        array $serializerFormats,
        LoggerInterface $logger,
        RelatedContentApiClass $relatedContentApiClass,
        Request $currentRequest,
        LanguageManager $languageManager
    ) {
        $this->relatedContentApiClass = $relatedContentApiClass;
        $this->currentRequest = $currentRequest;
        $this->languageManager = $languageManager;
        $this->availableLanguages = $this->languageManager->getLanguages();
        $this->categoryId = self::setCategoryId();
        $this->languageId = self::setLanguageId();
        $this->prisonId = self::setPrisonId();
        $this->numberOfResults = self::setNumberOfResults();
        $this->offsetIntoNumberOfResults = self::setOffsetIntoNumberOfResults();
        $this->sortOrder = self::setSortOrder();
        self::checkLanguageIdIsValid();
        self::checkCategoryIdIsNumeric();
        self::checkNumberOfResultsIsNumeric();
        self::checkOffsetIntoNumberOfResultsIsNumeric();
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
            $container->get('moj_resources.related_content_api_class'),
            $container->get('request_stack')->getCurrentRequest(),
            $container->get('language_manager')
        );
    }

    public function get()
    {
        $relatedContent = $this->relatedContentApiClass->RelatedContentApiEndpoint(
            $this->languageId,
            $this->categoryId,
            $this->numberOfResults,
            $this->offsetIntoNumberOfResults,
            $this->prisonId,
            $this->sortOrder
        );
        if (empty($relatedContent)) {
            throw new NotFoundHttpException(t('No related content found'));
        }

        $response = new ResourceResponse($relatedContent);
        $response->addCacheableDependency($relatedContent);
        return $response;
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

    protected function checkCategoryIdIsNumeric()
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

    protected function checkOffsetIntoNumberOfResultsIsNumeric()
    {
        if (is_numeric($this->offsetIntoNumberOfResults)) {
            return true;
        }
        throw new NotFoundHttpException(
            t('The offset of results parameter must be a numeric'),
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

    protected function setNumberOfResults()
    {
        return is_null($this->currentRequest->get('_number')) ? 8 : $this->currentRequest->get('_number');
    }

    protected function setOffsetIntoNumberOfResults()
    {
        return is_null($this->currentRequest->get('_offset')) ? 0 : $this->currentRequest->get('_offset');
    }

    protected function setPrisonId()
    {
        return is_null($this->currentRequest->get('_prison')) ? 0 : $this->currentRequest->get('_prison');
    }

    protected function setSortOrder()
    {
        return is_null($this->currentRequest->get('_sort_order')) ? 'ASC' : $this->currentRequest->get('_sort_order');
    }
}


