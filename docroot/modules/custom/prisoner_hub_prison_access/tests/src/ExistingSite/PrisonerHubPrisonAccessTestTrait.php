<?php

namespace Drupal\Tests\prisoner_hub_prison_access\ExistingSite;

use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Provides helper methods and properties for testing prison entity access.
 */
trait PrisonerHubPrisonAccessTestTrait {

  /**
   * The "current" prison taxonomy term.
   *
   * @var \Drupal\taxonomy\Entity\Term
   */
  protected $prisonTerm;

  /**
   * Another prison taxonomy term, that is _not_ the "current".
   *
   * @var \Drupal\taxonomy\Entity\Term
   */
  protected $anotherPrisonTerm;

  /**
   * A prison category term, that is associated with the "current" prison.
   *
   * @var \Drupal\taxonomy\Entity\Term
   */
  protected $prisonCategoryTerm;

  /**
   * The prison category term machine name.
   *
   * @var string
   */
  protected $prisonTermMachineName;

  /**
   * Another prison category term that isn't associated with the current prison.
   *
   * @var \Drupal\taxonomy\Entity\Term
   */
  protected $anotherPrisonCategoryTerm;

  /**
   * The prison reference field name.
   *
   * @var string
   */
  protected $prisonFieldName;

  /**
   * The excluded from prison reference field name.
   *
   * @var string
   */
  protected $excludeFromPrisonFieldName;

  /**
   * Generate some prison taxonomy terms that can be used for testing.
   */
  protected function createPrisonTaxonomyTerms() {

    $this->prisonFieldName = $this->container->getParameter('prisoner_hub_prison_access.prison_field_name');
    $this->excludeFromPrisonFieldName = $this->container->getParameter('prisoner_hub_prison_access.exclude_from_prison_field_name');

    $vocab_prisons = Vocabulary::load('prisons');
    $this->prisonCategoryTerm = $this->createTerm($vocab_prisons);

    $vocab_prisons = Vocabulary::load('prisons');
    $values = [
      'parent' => [
        ['target_id' => $this->prisonCategoryTerm->id()],
      ],
    ];
    $this->prisonTerm = $this->createTerm($vocab_prisons, $values);
    $this->prisonTermMachineName = $this->prisonTerm->get('machine_name')->getValue()[0]['value'];

    // Create alternative prison and prison category taxonomy terms.
    // We will tag some content with this, to ensure it does not appear.
    $this->anotherPrisonCategoryTerm = $this->createTerm($vocab_prisons);
    $values = [
      'parent' => [
        ['target_id' => $this->anotherPrisonCategoryTerm->id()],
      ],
    ];
    $this->anotherPrisonTerm = $this->createTerm($vocab_prisons, $values);
  }

  /**
   * Get the bundles for an $entityType that the prison field is attached to.
   *
   * @param string $entityType
   *   The entity type id to search.
   * @param string $fieldName
   *   Name of the prison field.
   *
   * @return array
   *   An array of bundle ids.
   */
  protected function getBundlesWithField(string $entityType, string $fieldName) {
    // Get the list of content types with the prison field enabled.
    /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager */
    $entityFieldManager = $this->container->get('entity_field.manager');
    $entityFieldManager->getFieldMap();
    return $entityFieldManager->getFieldMap()[$entityType][$fieldName]['bundles'];
  }

}
