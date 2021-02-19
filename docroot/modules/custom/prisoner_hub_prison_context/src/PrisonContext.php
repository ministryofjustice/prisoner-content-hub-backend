<?php

namespace Drupal\prisoner_hub_prison_context;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\ParamConverter\ParamConverterInterface;
use Symfony\Component\Routing\Route;

/**
 * Class PrisonContext.
 */
class PrisonContext implements ParamConverterInterface {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The loaded prison taxonomy term.
   *
   * @var \Drupal\taxonomy\Entity\Term
   */
  protected $prisonTaxonomyTerm;

  protected $prisonCategoryFieldName;

  /**
   * Constructs a new PrisonContext object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->prisonCategoryFieldName = 'field_prison_categories';
  }

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    if (!empty($value)) {
      return $this->getPrisonTerm($value);
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route) {
    return !empty($definition['type']) && $definition['type'] == 'prison_context';
  }

  public function getPrisonTerm($name) {
    if (is_null($this->prisonTaxonomyTerm)) {
      $query = $this->entityTypeManager->getStorage('taxonomy_term')->getQuery();
      $query->condition('name', $name);
      $tid = $query->execute();
      if ($tid) {
        $this->prisonTaxonomyTerm = $this->entityTypeManager->getStorage('taxonomy_term')->load(reset($tid));
      }
      else {
        $this->prisonTaxonomyTerm = NULL;
      }
    }
    return $this->prisonTaxonomyTerm;
  }

  public function getPrisonCategoryFieldName() {
    return $this->prisonCategoryFieldName;
  }

  public function getPrisonCategories() {
    $term = $this->getPrisonTerm();
    if ($term) {
      return $term->get($this->getPrisonCategoryFieldName())->getValue();
    }
    return [];
  }

  public function prisonContextExists() {
    return (bool)$this->getPrisonTerm();
  }

}
