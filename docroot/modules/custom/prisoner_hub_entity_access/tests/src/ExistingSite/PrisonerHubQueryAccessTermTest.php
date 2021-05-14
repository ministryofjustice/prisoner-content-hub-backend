<?php

namespace Drupal\Tests\prisoner_hub_entity_access\ExistingSite;

use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Test that the jsonapi responses for taxonomy terms tagged with prisons and
 * prison categories return the correct response.
 *
 * @group prisoner_hub_entity_access
 */
class PrisonerHubQueryAccessTermTest extends PrisonerHubQueryAccessTestBase {

  protected $entityTypeId = 'taxonomy_term';

  /**
   * {@inheritdoc}
   */
  protected function getEntityTypeId() {
    return 'taxonomy_term';
  }

  /**
   * {@inheritdoc}
   */
  protected function createEntity(string $bundle, array $values) {
    $vocabulary = Vocabulary::load($bundle);
    return $this->createTerm($vocabulary, $values);
  }
}
