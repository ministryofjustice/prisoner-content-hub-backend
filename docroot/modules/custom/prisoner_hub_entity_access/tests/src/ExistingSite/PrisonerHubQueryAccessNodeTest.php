<?php

namespace Drupal\Tests\prisoner_hub_entity_access\ExistingSite;

/**
 * Test that the jsonapi responses for nodes tagged with prisons and
 * prison categories return the correct response.
 *
 * @group prisoner_hub_entity_access
 */
class PrisonerHubQueryAccessNodeTest extends PrisonerHubQueryAccessTestBase {

  protected $entityTypeId = 'taxonomy_term';

  /**
   * {@inheritdoc}
   */
  protected function getEntityTypeId() {
    return 'node';
  }

  /**
   * {@inheritdoc}
   */
  protected function createEntity(string $bundle, array $values) {
    $values['type'] = $bundle;
    return $this->createNode($values);
  }
}
