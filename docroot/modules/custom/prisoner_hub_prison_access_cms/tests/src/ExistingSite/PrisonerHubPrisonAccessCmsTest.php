<?php

namespace Drupal\Tests\prisoner_hub_prison_access_cms\ExistingSite;

use Drupal\node\NodeInterface;
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

    // Temporarily remove urgent banner content type, as this is not yet
    // accessible local content managers.
    // TODO: Remove these lines (re-instate tests) for urgent banner content type.
    $key = array_search('urgent_banner', $this->contentTypes);
    unset($this->contentTypes[$key]);

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
      if (in_array($contentType, ['featured_articles', 'homepage'])) {
        continue;
      }
      $this->visit('/node/add/' . $contentType);

      // Test that the default value for the prison owner field is the users current prison.
      $this->assertSession()->elementAttributeExists('css', 'select[name="field_prison_owner[]"] option[value="' . $this->prisonTerm->id() . '"]', 'selected');

      $this->assertUserCanEditNodeOnCurrentPage($contentType);
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

      $this->assertUserCanEditNode($node);
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

      $this->assertUserCanEditNode($node);
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

      $this->assertUserCanEditNode($node);
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

      $this->assertUserCanEditNode($node);
    }
  }

  /**
   * Test the a user can edit content that is owned by multiple prisons.
   */
  public function testUserCanEditContentInMultiplePrisons() {
    foreach ($this->contentTypes as $contentType) {
      $node = $this->createNode([
        'type' => $contentType,
        $this->prisonOwnerFieldName => [
          ['target_id' => $this->prisonTerm->id()],
          ['target_id' => $this->anotherPrisonTerm->id()],
        ],
        'uid' => 1, // Set to admin user 1, i.e. NOT the test user.
      ]);

      $this->assertUserCanEditNode($node);
    }
  }

  /**
   * Test that a user with 'bypass prison ownership edit access' can edit content.
   */
  public function testUserWithByPassPermissionCanEditContent() {
    $this->drupalLogout();
    $new_user = $this->createUser([
      'administer nodes',
      'bypass node access',
      'bypass prison ownership edit access',
    ]);
    $new_user->save();
    $this->drupalLogin($new_user);
    foreach ($this->contentTypes as $contentType) {
      $node = $this->createNode([
        'type' => $contentType,
        $this->prisonOwnerFieldName => [
          ['target_id' => $this->prisonTerm->id()]
        ],
        'uid' => 1, // Set to admin user 1, i.e. NOT the test user.
      ]);

      $this->assertUserCanEditNode($node);
    }
  }

  /**
   * Test the a user can edit their own content even if it's not owned by a prison.
   */
  public function testUserCanEditOwnAuthoredContent() {
    foreach ($this->contentTypes as $contentType) {
      $node = $this->createNode([
        'type' => $contentType,
        'uid' => $this->user->id(),
      ]);

      $this->assertUserCanEditNode($node);
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

      $fieldPrisonElement = $this->assertSession()->elementExists('css', '#edit-field-prisons');

      // Check user can add their own prison to the content.
      $this->assertSession()->fieldEnabled($this->prisonTerm->label(), $fieldPrisonElement);
      // Check user cannot modify other prisons from the content.
      $this->assertSession()->fieldDisabled($this->anotherPrisonTerm->label(), $fieldPrisonElement);

      $fieldExcludeFromPrisonElement = $this->assertSession()->elementExists('css', '#edit-field-exclude-from-prison');

      // Check user can exclude their own prison from the content.
      $this->assertSession()->fieldEnabled($this->prisonTerm->label(), $fieldExcludeFromPrisonElement);
      // Check the user cannot exclude another prison from the content.
      $this->assertSession()->fieldDisabled($this->anotherPrisonTerm->label(), $fieldExcludeFromPrisonElement);
    }
  }

  /**
   * Test that a user making changes to prison fields does not wipe previous values.
   *
   * @covers prisoner_hub_prison_access_cms_entity_presave()
   */
  public function testOtherPrisonsDoNotGetRemoved() {
    $category_tern = $this->createTerm(Vocabulary::load('moj_categories'));
    $node = $this->createNode([
      // Only test on basic pages for now, as other content types have file fields
      // that we would need to fill out in our tests.
      'type' => 'page',
      'field_exclude_from_prison' => [
        ['target_id' => $this->anotherPrisonTerm->id()],
      ],
      'field_prisons' => [
        ['target_id' => $this->anotherPrisonTerm->id()],
      ],
      'field_moj_description' => [
        'value' => $this->randomString(),
        'summary' => $this->randomString(),
        'format' => 'plain_text',
      ],
      'field_prison_owner' => [
        ['target_id' => $this->prisonTerm->id()],
      ],
      'field_not_in_series' => [
        'value' => 1,
      ],
      'field_moj_top_level_categories' => [
        ['target_id' => $category_tern->id()]
      ]

    ]);
    $edit_url = $node->toUrl('edit-form');
    $this->visit($edit_url->toString());

    // Update the the prison field.
    $prisonFieldElement = $this->assertSession()->elementExists('css', '#edit-field-prisons');
    $prisonFieldElement->checkField($this->prisonTerm->label());

    // Update the exclude from prison field.
    $fieldExcludeFromPrisonElement = $this->assertSession()->elementExists('css', '#edit-field-exclude-from-prison');
    $fieldExcludeFromPrisonElement->checkField($this->prisonTerm->label());

    $this->submitForm([], 'Save');
    $message = "Basic page ". $node->label() . " has been updated.";
    $this->assertSession()->pageTextContains($message);

    $prisonFieldElement = $this->assertSession()->elementExists('css', '#edit-field-prisons');
    $fieldExcludeFromPrisonElement = $this->assertSession()->elementExists('css', '#edit-field-exclude-from-prison');
    $this->assertSession()->checkboxChecked($this->prisonTerm->label(), $prisonFieldElement);
    $this->assertSession()->checkboxChecked($this->anotherPrisonTerm->label(), $prisonFieldElement);
    $this->assertSession()->checkboxChecked($this->prisonTerm->label(), $fieldExcludeFromPrisonElement);
    $this->assertSession()->checkboxChecked($this->anotherPrisonTerm->label(), $fieldExcludeFromPrisonElement);
  }

  /**
   * Test that a user without the assign prisons to users cannot add prisons to a user.
   */
  public function testUserCannotEditUserPrisons() {
    $user_edit_url = $this->user->toUrl('edit-form');
    $this->visit($user_edit_url->toString());
    $fieldUserPrisonsElement = $this->assertSession()->elementExists('css', '#edit-field-user-prisons');

    // First check the user prison field is on the page. (I.e. there are some checkboxes).
    $this->assertSession()->elementExists('css', 'input[type="checkbox"]', $fieldUserPrisonsElement);

    // Check that they are all disabled, we don't want to find any checkboxes
    // that do not have a disabled state.
    $this->assertSession()->elementNotExists('css', 'input[type="checkbox"]:not([disabled])', $fieldUserPrisonsElement);
  }

  /**
   * Asserts that the user can make edits to the $node.
   *
   * @param \Drupal\node\NodeInterface $node
   */
  protected function assertUserCanEditNode(NodeInterface $node) {
    $edit_url = $node->toUrl('edit-form');
    $this->visit($edit_url->toString());
    $this->assertUserCanEditNodeOnCurrentPage($node->getType());
  }

  /**
   * Asserts that the user can edit the node on the current page.
   *
   * Assumes the page is already in the current session.
   *
   * @param string $contentType
   *   The content type of the current page.
   */
  protected function assertUserCanEditNodeOnCurrentPage(string $contentType) {
    // Test some fields are enabled, that appear on all content types.
    try {
      $this->assertSession()->fieldEnabled('Title');
      $this->assertSession()->fieldEnabled('Published');
    }
    catch (\Exception $e) {
      $this->fail("Unable to edit the $contentType content type. Error message: " . $e->getMessage());
    }

    try {
      // Test that the user is able to select prisons.
      $fieldPrisonElement = $this->assertSession()->elementExists('css', '#edit-field-prisons');
      $this->assertSession()->fieldEnabled($this->prisonTerm->label(), $fieldPrisonElement);
      $this->assertSession()->fieldEnabled($this->anotherPrisonTerm->label(), $fieldPrisonElement);
    }
    catch (\Exception $e) {
      $this->fail("Unable to use prison fields on the $contentType content type. Error message: " . $e->getMessage());
    }
  }
}
