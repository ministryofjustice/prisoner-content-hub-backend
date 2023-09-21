<?php

namespace Drupal\Tests\prisoner_hub_edit_only\ExistingSite;

use Drupal\Core\Entity\EntityInterface;
use Drupal\node\NodeInterface;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Tests for redirecting views for certain entities to views.
 *
 * @group prisoner_hub_edit_only
 */
class EditOnlyTest extends ExistingSiteBase {

  /**
   * An array of entities to test.
   *
   * @var array
   */
  protected $entitiesToTest;

  /**
   * An array of entities to test.
   *
   * @var array
   */
  protected array $entitiesToTestExcluded;

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

    $this->entitiesToTestExcluded = [];
    $node = $this->createNode([
      'status' => NodeInterface::PUBLISHED,
      'type' => 'help_page',
    ]);
    $this->entitiesToTestExcluded[] = $node;
  }

  /**
   * Test viewing an entity redirects user to the edit page.
   */
  public function testViewRedirectsToEdit() {
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    foreach ($this->entitiesToTest as $entity) {
      $view_url = $entity->toUrl();
      $edit_url = $entity->toUrl('edit-form');

      // Test alias paths like /content/123.
      $this->visit($view_url->toString());
      $web_assert = $this->assertSession();
      $web_assert->addressEquals($edit_url->toString());

      // Test internal paths like /node/123.
      $this->visit('/' . $view_url->getInternalPath());
      $web_assert = $this->assertSession();
      $web_assert->addressEquals($edit_url->toString());
    }
  }

  /**
   * Test viewing an excluded entity doesn't redirect the user.
   */
  public function testExcludedContentTypes() {
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    foreach ($this->entitiesToTestExcluded as $entity) {
      $view_url = $entity->toUrl();

      // Test alias paths like /content/123.
      $this->visit($view_url->toString());
      $web_assert = $this->assertSession();
      $web_assert->statusCodeEquals(200);
      $web_assert->addressEquals($view_url->toString());

      // Test internal paths like /node/123.
      $this->visit('/' . $view_url->getInternalPath());
      $web_assert = $this->assertSession();
      $web_assert->statusCodeEquals(200);
      $web_assert->addressEquals('/' . $view_url->getInternalPath());
    }
  }

  /**
   * Test that entities can be saved, and subsequently re-saved.
   */
  public function testEntityCanBeReSaved() {
    // Create an admin user to perform the tests with.
    $account = $this->createUser([], NULL, TRUE);
    $this->drupalLogin($account);

    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    foreach ($this->entitiesToTest as $entity) {
      $edit_url = $entity->toUrl('edit-form');
      $this->visit($edit_url->toString());

      // "title" for node, "name" for taxonomy term.
      $label_key = $entity->getEntityType()->getKey('label');

      // Run through the changes twice.  This tests for the issue where
      // content could not be re-saved. See https://trello.com/c/e6MQjFUu/587-fix-this-content-has-been-modified-by-another-user-issue
      for ($counter = 1; $counter <= 2; $counter++) {
        $this->submitForm([
          $label_key . '[0][value]' => 'Label: ' . $counter,
        ], 'Save');

        $this->assertSession()->addressEquals($edit_url->toString());
        $new_entity = $this->reloadEntity($entity);
        $this->assertEquals($new_entity->label(), 'Label: ' . $counter);
      }
    }
  }

  /**
   * Reloads the entity after clearing the static cache.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to reload.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The reloaded entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown if the entity type doesn't exist.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown if the storage handler couldn't be loaded.
   */
  protected function reloadEntity(EntityInterface $entity) {
    $storage = \Drupal::entityTypeManager()->getStorage($entity->getEntityTypeId());
    $storage->resetCache([$entity->id()]);
    return $storage->load($entity->id());
  }

}
