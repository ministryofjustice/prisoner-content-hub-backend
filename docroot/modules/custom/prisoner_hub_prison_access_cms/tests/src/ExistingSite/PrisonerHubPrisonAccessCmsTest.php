<?php

namespace Drupal\Tests\prisoner_hub_prison_access_cms\ExistingSite;

use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\prisoner_hub_prison_access\ExistingSite\PrisonerHubPrisonAccessTestTrait;
use weitzman\DrupalTestTraits\ExistingSiteBase;

class PrisonerHubPrisonAccessCmsTest extends ExistingSiteBase {

  use PrisonerHubPrisonAccessTestTrait;

  /**
   * The role id to test with.
   *
   * @var string
   */
  static $role = 'moj_local_content_manager';

  /**
   * The content types to test on, an array of bundle ids.
   *
   * @var array
   */
  protected $contentTypes;

  /**
   * The generated user for testing.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $user;

  /**
   * Create prison taxonomy terms and a user to test with.
   */
  public function setUp(): void {
    parent::setUp();
    $this->createPrisonTaxonomyTerms();

    $this->prisonOwnerFieldName = $this->container->getParameter('prisoner_hub_prison_access_cms.prison_owner_field_name');
    $this->userPrisonFieldName = $this->container->getParameter('prisoner_hub_prison_access_cms.user_prison_field_name');
    $this->contentTypes = $this->getBundlesWithField('node', $this->prisonOwnerFieldName);
    $this->user = $this->createUser([], NULL, FALSE, [
      $this->userPrisonFieldName => [
        ['target_id' => $this->prisonTerm->id()],
      ]
    ]);
    $this->user->addRole(self::$role);
    $this->user->save();
    $this->drupalLogin($this->user);
  }

  /**
   * Test that the user can create new content.
   */
  public function testUserCanCreateNewContent() {
    foreach ($this->contentTypes as $contentType) {
      // Skip the homepage content type, as we do not have access to create these.
      if ($contentType == 'featured_articles') {
        continue;
      }
      $this->visit('/node/add/' . $contentType);

      // Test that the default value for the prison owner field is the users current prison.
      $this->assertSession()->elementAttributeExists('css', 'select[name="field_prison_owner[]"] option[value="' . $this->prisonTerm->id() . '"]', 'selected');

      $this->assertUserCanEditNode();
    }
  }

  /**
   * Tests that a user can edit content that is created by another user but assigned to their prison.
   */
  public function testUserCanEditOwnPrisonContent() {
    foreach ($this->contentTypes as $contentType) {
      $node = $this->createNode([
        'type' => $contentType,
        $this->prisonOwnerFieldName => [
          ['target_id' => $this->prisonCategoryTerm->id()]
        ],
        'uid' => 1, // Set to admin user 1, i.e. NOT the test user.
      ]);

      $edit_url = $node->toUrl('edit-form');
      $this->visit($edit_url->toString());
      $this->assertUserCanEditNode();
    }
  }

  /**
   * Test user can edit content that is assigned to their prison category.
   */
  public function testUserCanEditContentAssignedToCategory() {
    foreach ($this->contentTypes as $contentType) {
      $node = $this->createNode([
        'type' => $contentType,
         $this->prisonOwnerFieldName => [
          ['target_id' => $this->prisonCategoryTerm->id()]
        ],
        'uid' => 1, // Set to admin user 1, i.e. NOT the test user.
      ]);

      $edit_url = $node->toUrl('edit-form');
      $this->visit($edit_url->toString());
      $this->assertUserCanEditNode();
    }
  }

  /**
   * Test that a user assigned to a prison category can edit content assigned to another prison in same category.
   */
  public function testUserCanEditContentInPrisonCategory() {
    // Assign a prison category to the user.
    $this->user->set($this->userPrisonFieldName, [
      ['target_id' => $this->prisonCategoryTerm->id()],
    ]);
    $this->user->save();

    $anotherPrisonInSameCategory = $this->createTerm(Vocabulary::load('prisons'), [
      'parent' => [
        ['target_id' => $this->prisonCategoryTerm->id()]
      ],
    ]);

    foreach ($this->contentTypes as $contentType) {
      $node = $this->createNode([
        'type' => $contentType,
        $this->prisonOwnerFieldName => [
          ['target_id' => $anotherPrisonInSameCategory->id()]
        ],
        'uid' => 1, // Set to admin user 1, i.e. NOT the test user.
      ]);

      $edit_url = $node->toUrl('edit-form');
      $this->visit($edit_url->toString());
      $this->assertUserCanEditNode();
    }
  }

  /**
   * Test that a user with multiple prisons can edit content owned by one of those prisons.
   */
  public function testUserWithMultiplePrisonsCanEditContent() {
    $new_prison = $this->createTerm( Vocabulary::load('prisons'));
    $this->user->set($this->userPrisonFieldName, [
      ['target_id' => $this->prisonTerm->id()],
      ['target_id' => $new_prison->id()],
    ]);
    $this->user->save();

    foreach ($this->contentTypes as $contentType) {
      $node = $this->createNode([
        'type' => $contentType,
        $this->prisonOwnerFieldName => [
          ['target_id' => $new_prison->id()]
        ],
        'uid' => 1, // Set to admin user 1, i.e. NOT the test user.
      ]);

      $edit_url = $node->toUrl('edit-form');
      $this->visit($edit_url->toString());
      $this->assertUserCanEditNode();
    }
  }

  /**
   * Tests that a user cannot make changes to content owned by another prison.
   */
  public function testUserCannotEditOtherUserPrisonContent() {
    foreach ($this->contentTypes as $contentType) {
      $node = $this->createNode([
        'type' => $contentType,
        $this->prisonOwnerFieldName => [
          ['target_id' => $this->anotherPrisonTerm->id()]
        ],
        'uid' => 1, // Set to admin user 1, i.e. NOT the test user.
      ]);
      $edit_url = $node->toUrl('edit-form');
      $this->visit($edit_url->toString());

      // Test some fields are disabled, that appear on all content types.
      $this->assertSession()->fieldDisabled('Title');
      $this->assertSession()->fieldDisabled('Published');

      // Test that the user is not able to change the prisons the content is published to.
      $fieldPrisonElement = $this->assertSession()->elementExists('css', '#edit-field-prisons');
      $this->assertSession()->fieldDisabled($this->prisonTerm->label(), $fieldPrisonElement);
      $this->assertSession()->fieldDisabled($this->anotherPrisonTerm->label(), $fieldPrisonElement);

      $fieldExcludeFromPrisonElement = $this->assertSession()->elementExists('css', '#edit-field-exclude-from-prison');

      // Check user can exclude their own prison from the content.
      $this->assertSession()->fieldEnabled($this->prisonTerm->label(), $fieldExcludeFromPrisonElement);
      // Check the user cannot exclude another prison from the content.
      $this->assertSession()->fieldDisabled($this->anotherPrisonTerm->label(), $fieldExcludeFromPrisonElement);
    }
  }

  /**
   * Asserts that the user can make edits to the current node edit form.
   *
   * Assumes we are already on the form url in the current session.
   */
  protected function assertUserCanEditNode() {
    // Test some fields are enabled, that appear on all content types.
    $this->assertSession()->fieldEnabled('Title');
    $this->assertSession()->fieldEnabled('Published');

    // Test that the user is able to select prisons.
    $fieldPrisonElement = $this->assertSession()->elementExists('css', '#edit-field-prisons');
    $this->assertSession()->fieldEnabled($this->prisonTerm->label(), $fieldPrisonElement);
    $this->assertSession()->fieldEnabled($this->anotherPrisonTerm->label(), $fieldPrisonElement);
  }
}
