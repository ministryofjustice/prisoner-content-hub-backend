<?php

namespace Drupal\moj_resources;

use Drupal\moj_resources\Utilities;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryFactory;

require_once('Utils.php');

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
        $this->termIds = self::getVocabularyTermIds($taxonomyName);
        $filteredTermIds = array();

        if ($taxonomyName == 'tags') {
          foreach ($this->termIds as $termId) {
            $contentForTerm = $this->getAllSecondaryTagItemsFor($termId);

            if (!empty($contentForTerm)) {
              array_push($filteredTermIds, $termId);
            }
          }
        } else {
          $filteredTermIds = $this->termIds;
        }

        $this->terms = $this->termStorage->loadMultiple($filteredTermIds);

        return array_map([$this, 'translateTerm'], $this->terms);
    }
    /**
     * Get matching secondary items for supplied tag ids
     *
     * @param array[int] $tagIds
     *
     * @return array
     */
    private function getAllSecondaryTagItemsFor($termId)
    {
      $types = array('page', 'moj_pdf_item', 'moj_radio_item', 'moj_video_item');

      $query = $this->entityQuery->get('node')
        ->condition('status', 1)
        ->condition('type', $types, 'IN')
        ->accessCheck(false);

      $query = getPrisonResults($this->prisonId, $query);

      $group = $query
        ->orConditionGroup()
        ->condition('field_moj_secondary_tags', $termId)
        ->condition('field_moj_tags', $termId);

      return $query
        ->condition($group)
        ->execute();
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

      $query = $this->entityQuery->get('taxonomy_term');
      $query->condition('vid', $taxonomyName);

      if ($taxonomyName == "series") {

        $prison = Utilities::getTermFor($this->prisonId, $this->termStorage);
        $prisonCategories = Utilities::getPrisonCategoriesFor($prison);

        $prisonCategoriesCondition = Utilities::filterByPrisonCategories(
          $this->prisonId,
          $prisonCategories,
          $query,
          true
        );

        $query->condition($prisonCategoriesCondition);

      }

      return $query->execute();
    }
}
