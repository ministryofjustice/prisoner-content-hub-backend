<?php

namespace Drupal\moj_resources;

use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Entity\Query\QueryFactory;
use Symfony\Component\Serializer\Serializer;
use Drupal\Core\Entity\EntityTypeManagerInterface;

require_once('Utils.php');

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
    protected $prisonId;

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

        return array_map('self::translateTerm', $this->terms);
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

      $query->condition('field_moj_secondary_tags', $termId);

      return $query
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
        return $this->entityQuery->get('taxonomy_term')
            ->condition('vid', $taxonomyName)
            ->execute();
    }
}
