<?php

namespace Drupal\moj_resources;

use Drupal\moj_resources\Utilities;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryFactory;

/**
 * PromotedContentApiClass
 */

class VocabularyApiClass
{
    /**
     * Terms
     *
     * @var array
     */
    protected $terms;
    /**
     * Language id
     *
     * @var string
     */
    protected $languageId;
    /**
     * Prison id
     *
     * @var string
     */
    protected $prisonId;
    /**
     * Term storage object
     *
     * @var EntityTypeManager
     */
    protected $termStorage;
    /**
     * Entity Query object
     *
     * @var QueryFactory
     *
     * Instance of QueryFactory
     */
    protected $entityQuery;

    /**
     * Class Constructor
     *
     * @param EntityTypeManager $entityTypeManager
     * @param QueryFactory $entityQuery
     */
    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        QueryFactory $entityQuery
    ) {
        $this->termStorage = $entityTypeManager->getStorage('taxonomy_term');
        $this->entityQuery = $entityQuery;
    }
    /**
     * API resource function
     *
     * @param int $languageId
     * @param string $taxonomyName
     * @return array
     */
    public function VocabularyApiEndpoint($languageId, $taxonomyName, $prisonId)
    {
        $this->languageId = $languageId;
        $this->prisonId = $prisonId;

        $termIds = $this->getVocabularyTermIds($taxonomyName);

        $this->terms = $this->termStorage->loadMultiple($termIds);

        return array_map([$this, 'translateTerm'], $this->terms);
    }
    /**
     * TranslateNode function
     *
     * @param NodeInterface $term
     *
     * @return NodeInterface
     */
    protected function translateTerm($term)
    {
        return $term->hasTranslation($this->languageId) ? $term->getTranslation($this->languageId) : $term;
    }
    /**
     * Get termIds
     *
     * @param string $taxonomyName
     *
     * @return NodeInterface[]
     */
    protected function getVocabularyTermIds($taxonomyName)
    {
        $prison = Utilities::getTermFor($this->prisonId, $this->termStorage);
        $prisonCategories = Utilities::getPrisonCategoriesFor($prison);

        $query = $this->entityQuery->get('taxonomy_term');

        $prisonCategoriesCondition = Utilities::filterByPrisonCategories(
          $this->prisonId,
          $prisonCategories,
          $query,
          true
        );

        $query
            ->condition('vid', $taxonomyName)
            ->condition($prisonCategoriesCondition);

        return $query->execute();
    }
}
