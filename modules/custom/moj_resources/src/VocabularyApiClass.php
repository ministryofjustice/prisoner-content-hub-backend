<?php

namespace Drupal\moj_resources;

use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Entity\Query\QueryFactory;
use Symfony\Component\Serializer\Serializer;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * PromotedContentApiClass
 */

class VocabularyApiClass
{
    /**
     * Term IDs
     *
     * @var array
     */
    protected $termIds = array();
    /**
     * Terms
     *
     * @var array
     */
    protected $terms;
    /**
     * Language Tag
     *
     * @var string
     */
    protected $languageId;
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
     * @param string $category
     * @return array
     */
    public function VocabularyApiEndpoint($languageId, $category)
    {
        $this->languageId = $languageId;
        $this->termIds = self::getVocabularyTermIds($category);
        $this->terms = $this->termStorage->loadMultiple($this->termIds);

        return array_map('self::translateTerm', $this->terms);
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
     * @param string $category
     *
     * @return NodeInterface[]
     */
    protected function getVocabularyTermIds($category)
    {
        return $this->entityQuery->get('taxonomy_term')
            ->condition('vid', $category)
            ->execute();
    }
}
