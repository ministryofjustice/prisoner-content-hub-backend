<?php

namespace Drupal\Tests\prisoner_hub_edit_only\ExistingSite;

use Drupal\node\NodeInterface;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * @group prisoner_hub_edit_only
 */
class EditOnlyTest extends ExistingSiteBase {

  /**
   * An array of entities to test.
   *
   * @var array.
   */
  protected $entitiesToTest;

  /**
   * Create entities to test with.
   */
  protected function setup() :void {
    parent::setUp();
    $this->entitiesToTest = [];
    $node = $this->createNode(['status' => NodeInterface::PUBLISHED]);
    $this->entitiesToTest[] = $node;
    $vocab = $this->createVocabulary();
    $this->entitiesToTest[] = $this->createTerm($vocab);
  }

  /**
   * Test that viewing an entity results in the user being redirected to the edit page.
   */
  public function testViewRedirectsToEdit() {
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    foreach ($this->entitiesToTest as $entity) {
      $view_url = $entity->toUrl();
      $edit_url = $entity->toUrl('edit-form');

      // Test alias paths like /content/123
      $this->visit($view_url->toString());
      $web_assert = $this->assertSession();
      $web_assert->addressEquals($edit_url->toString());

      // Test internal paths like /node/123
      $this->visit('/' . $view_url->getInternalPath());
      $web_assert = $this->assertSession();
      $web_assert->addressEquals($edit_url->toString());
    }
  }
}
