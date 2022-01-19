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
   * The content types to test on.
   *
   * @var string[]
   */
  static $contentTypes = ['moj_video_item'];

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
    $this->user = $this->createUser([], NULL, FALSE, [
      'field_user_prisons' => [
        ['target_id' => $this->prisonTerm->id()]
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
    foreach (self::$contentTypes as $contentType) {
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
    foreach (self::$contentTypes as $contentType) {
      $node = $this->createNode([
        'type' => $contentType,
        'field_prison_owner' => [
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
    foreach (self::$contentTypes as $contentType) {
      $node = $this->createNode([
        'type' => $contentType,
        'field_prison_owner' => [
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
    $this->user->set('field_user_prisons', [
      ['target_id' => $this->prisonCategoryTerm->id()],
    ]);
    $this->user->save();

    $anotherPrisonInSameCategory = $this->createTerm(Vocabulary::load('prisons'), [
      'parent' => [
        ['target_id' => $this->prisonCategoryTerm->id()]
      ],
    ]);

    foreach (self::$contentTypes as $contentType) {
      $node = $this->createNode([
        'type' => $contentType,
        'field_prison_owner' => [
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
   * Tests that a user cannot make changes to content owned by another prison.
   */
  public function testUserCannotEditOtherUserPrisonContent() {
    foreach (self::$contentTypes as $contentType) {
      $node = $this->createNode([
        'type' => $contentType,
        'field_prison_owner' => [
          ['target_id' => $this->anotherPrisonTerm->id()]
        ],
        'uid' => 1, // Set to admin user 1, i.e. NOT the test user.
      ]);
      $edit_url = $node->toUrl('edit-form');
      $this->visit($edit_url->toString());

      // Test some fields are disabled, that appear on all content types.
      $this->assertSession()->fieldDisabled('Title');
      $this->assertSession()->fieldDisabled('Description');

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

  protected function assertUserCanEditNode() {
    // Test some fields are enabled, that appear on all content types.
    $this->assertSession()->fieldEnabled('Title');
    $this->assertSession()->fieldEnabled('Description');

    // Test that the user is able to select prisons.
    $fieldPrisonElement = $this->assertSession()->elementExists('css', '#edit-field-prisons');
    $this->assertSession()->fieldEnabled($this->prisonTerm->label(), $fieldPrisonElement);
    $this->assertSession()->fieldEnabled($this->anotherPrisonTerm->label(), $fieldPrisonElement);
  }
}
