<?php

namespace Drupal\Tests\prisoner_hub_prison_access_cms\ExistingSite;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Entity\EntityMalformedException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\prisoner_hub_prison_access\ExistingSite\PrisonerHubPrisonAccessTestTrait;
use Drupal\Tests\prisoner_hub_test_traits\Traits\NodeCreationTrait;
use Drupal\user\UserInterface;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Tests concerning access rules for prison-assigned content.
 */
class PrisonerHubPrisonAccessCmsTest extends ExistingSiteBase {

  use NodeCreationTrait;
  use PrisonerHubPrisonAccessTestTrait;

  /**
   * The role id to test with.
   *
   * @var string
   */
  private static string $role = 'moj_local_content_manager';

  /**
   * The content types to test on, an array of bundle ids.
   *
   * @var array
   */
  protected array $contentTypes;

  /**
   * The generated user for testing.
   *
   * @var \Drupal\user\Entity\User
   */
  protected UserInterface $user;

  /**
   * Name of the field denoting the prison that owns the content.
   */
  protected string $prisonOwnerFieldName;

  /**
   * Name of the field denoting to which prison a user belongs.
   */
  protected string $userPrisonFieldName;

  /**
   * Moderation information service.
   */
  protected ModerationInformationInterface $moderationInformation;

  /**
   * Entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Create prison taxonomy terms and a user to test with.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   If the user creation fails.
   */
  public function setUp(): void {
    parent::setUp();
    $this->createPrisonTaxonomyTerms();

    $this->prisonOwnerFieldName = $this->container->getParameter('prisoner_hub_prison_access_cms.prison_owner_field_name');
    $this->userPrisonFieldName = $this->container->getParameter('prisoner_hub_prison_access_cms.user_prison_field_name');
    $this->contentTypes = $this->getBundlesWithField('node', $this->prisonOwnerFieldName);

    $this->moderationInformation = $this->container->get('content_moderation.moderation_information');
    $this->entityTypeManager = $this->container->get('entity_type.manager');

    $this->user = $this->createUser([], NULL, FALSE, [
      $this->userPrisonFieldName => [
        ['target_id' => $this->prisonTerm->id()],
      ],
    ]);
    $this->user->addRole(self::$role);
    $this->user->save();
    $this->drupalLogin($this->user);
  }

  /**
   * Test that the user can create new content.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ElementHtmlException
   */
  public function testUserCanCreateNewContent() {
    foreach ($this->contentTypes as $contentType) {
      // Skip the homepage content type, as moj_local_content_manager role
      // does not have access to create these.
      if ($contentType == 'homepage') {
        continue;
      }
      $this->visit('/node/add/' . $contentType);

      // Test that the default value for the prison owner field is the users
      // current prison.
      $this->assertSession()->elementAttributeExists('css', 'select[name="field_prison_owner[]"] option[value="' . $this->prisonTerm->id() . '"]', 'selected');

      $this->assertUserCanEditNodeOnCurrentPage($contentType);
    }
  }

  /**
   * Tests that a user can edit others content for their prison.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function testUserCanEditOwnPrisonContent() {
    foreach ($this->contentTypes as $contentType) {
      $node = $this->createCategorisedNode([
        'type' => $contentType,
        $this->prisonOwnerFieldName => [
          ['target_id' => $this->prisonCategoryTerm->id()],
        ],
        // Set to admin user 1, i.e. NOT the test user.
        'uid' => 1,
      ]);

      $this->assertUserCanEditNode($node, FALSE);
    }
  }

  /**
   * Test user can edit content that is assigned to their prison category.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function testUserCanEditContentAssignedToCategory() {
    foreach ($this->contentTypes as $contentType) {
      $node = $this->createCategorisedNode([
        'type' => $contentType,
        $this->prisonOwnerFieldName => [
          ['target_id' => $this->prisonCategoryTerm->id()],
        ],
        // Set to admin user 1, i.e. NOT the test user.
        'uid' => 1,
      ]);

      $this->assertUserCanEditNode($node);
    }
  }

  /**
   * Test user access to other prisons' content in their category.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testUserCanEditContentInPrisonCategory() {
    // Assign a prison category to the user.
    $this->user->set($this->userPrisonFieldName, [
      ['target_id' => $this->prisonCategoryTerm->id()],
    ]);
    $this->user->save();

    $anotherPrisonInSameCategory = $this->createTerm(Vocabulary::load('prisons'), [
      'parent' => [
        ['target_id' => $this->prisonCategoryTerm->id()],
      ],
    ]);

    foreach ($this->contentTypes as $contentType) {
      $node = $this->createCategorisedNode([
        'type' => $contentType,
        $this->prisonOwnerFieldName => [
          ['target_id' => $anotherPrisonInSameCategory->id()],
        ],
        // Set to admin user 1, i.e. NOT the test user.
        'uid' => 1,
      ]);

      $this->assertUserCanEditNode($node);
    }
  }

  /**
   * Test user access to content where user has multiple prisons.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testUserWithMultiplePrisonsCanEditContent() {
    $new_prison = $this->createTerm(Vocabulary::load('prisons'));
    $this->user->set($this->userPrisonFieldName, [
      ['target_id' => $this->prisonTerm->id()],
      ['target_id' => $new_prison->id()],
    ]);
    $this->user->save();

    foreach ($this->contentTypes as $contentType) {
      $node = $this->createCategorisedNode([
        'type' => $contentType,
        $this->prisonOwnerFieldName => [
          ['target_id' => $new_prison->id()],
        ],
        // Set to admin user 1, i.e. NOT the test user.
        'uid' => 1,
      ]);

      $this->assertUserCanEditNode($node);
    }
  }

  /**
   * Test a user can edit content that is owned by multiple prisons.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function testUserCanEditContentInMultiplePrisons() {
    foreach ($this->contentTypes as $contentType) {
      $node = $this->createCategorisedNode([
        'type' => $contentType,
        $this->prisonOwnerFieldName => [
          ['target_id' => $this->prisonTerm->id()],
          ['target_id' => $this->anotherPrisonTerm->id()],
        ],
        // Set to admin user 1, i.e. NOT the test user.
        'uid' => 1,
      ]);

      $this->assertUserCanEditNode($node);
    }
  }

  /**
   * Test a user with 'bypass prison ownership edit access' can edit content.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\Core\Entity\EntityStorageException
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
      $node = $this->createCategorisedNode([
        'type' => $contentType,
        $this->prisonOwnerFieldName => [
          ['target_id' => $this->prisonTerm->id()],
        ],
        // Set to admin user 1, i.e. NOT the test user.
        'uid' => 1,
      ]);

      $this->assertUserCanEditNode($node, FALSE);
    }
  }

  /**
   * Test a user can edit their own content even if it's not owned by a prison.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function testUserCanEditOwnAuthoredContent() {
    foreach ($this->contentTypes as $contentType) {
      $node = $this->createCategorisedNode([
        'type' => $contentType,
        'uid' => $this->user->id(),
      ]);

      $this->assertUserCanEditNode($node);
    }
  }

  /**
   * Tests that a user cannot make changes to content owned by another prison.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testUserCannotEditOtherUserPrisonContent() {
    foreach ($this->contentTypes as $contentType) {
      $node = $this->createCategorisedNode([
        'type' => $contentType,
        $this->prisonOwnerFieldName => [
          ['target_id' => $this->anotherPrisonTerm->id()],
        ],
        // Set to admin user 1, i.e. NOT the test user.
        'uid' => 1,
      ]);
      $edit_url = $node->toUrl('edit-form');
      $this->visit($edit_url->toString());

      // Test some fields are disabled, that appear on all content types.
      $this->assertSession()->fieldDisabled('title[0][value]');
      $publishedField = $this->moderationInformation->isModeratedEntity($node) ? 'Change to' : 'Published';
      $this->assertSession()->fieldDisabled($publishedField);

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
   * Test a user making changes to prison fields does not wipe previous values.
   *
   * @covers ::prisoner_hub_prison_access_cms_entity_presave()
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testOtherPrisonsDoNotGetRemoved() {
    $series = $this->createTerm(Vocabulary::load('series'));
    $node = $this->createNode([
      // Only test on basic pages, as other content types have file fields
      // that we would need to fill out in our tests.
      'type' => 'page',
      'field_exclude_from_prison' => [
        ['target_id' => $this->anotherPrisonTerm->id()],
      ],
      'field_prisons' => [
        ['target_id' => $this->anotherPrisonTerm->id()],
      ],
      'field_summary' => [
        'value' => $this->randomString(),
      ],
      'field_main_body_content' => [
        'value' => $this->randomString(),
        'format' => 'plain_text',
      ],
      'field_prison_owner' => [
        ['target_id' => $this->prisonTerm->id()],
      ],
      'field_moj_series' => [
        ['target_id' => $series->id()],
      ],
      'moderation_state' => 'draft',
    ]);
    $edit_url = $node->toUrl('edit-form');
    $this->visit($edit_url->toString());

    // Update the prison field.
    $prisonFieldElement = $this->assertSession()->elementExists('css', '#edit-field-prisons');
    $prisonFieldElement->checkField($this->prisonTerm->label());

    // Update the exclude from prison field.
    $fieldExcludeFromPrisonElement = $this->assertSession()->elementExists('css', '#edit-field-exclude-from-prison');
    $fieldExcludeFromPrisonElement->checkField($this->prisonTerm->label());

    $this->submitForm([], 'Save');
    $message = "Basic page " . $node->label() . " has been updated.";
    $this->assertSession()->pageTextContains($message);

    $prisonFieldElement = $this->assertSession()->elementExists('css', '#edit-field-prisons');
    $fieldExcludeFromPrisonElement = $this->assertSession()->elementExists('css', '#edit-field-exclude-from-prison');
    $this->assertSession()->checkboxChecked($this->prisonTerm->label(), $prisonFieldElement);
    $this->assertSession()->checkboxChecked($this->anotherPrisonTerm->label(), $prisonFieldElement);
    $this->assertSession()->checkboxChecked($this->prisonTerm->label(), $fieldExcludeFromPrisonElement);
    $this->assertSession()->checkboxChecked($this->anotherPrisonTerm->label(), $fieldExcludeFromPrisonElement);
  }

  /**
   * Test a user without the assign prisons to users cannot do so.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testUserCannotEditUserPrisons() {
    $user_edit_url = $this->user->toUrl('edit-form');
    $this->visit($user_edit_url->toString());
    $fieldUserPrisonsElement = $this->assertSession()->elementExists('css', '#edit-field-user-prisons');

    // First check the user prison field is on the page.
    // I.E. there are some checkboxes.
    $this->assertSession()->elementExists('css', 'input[type="checkbox"]', $fieldUserPrisonsElement);

    // Check that they are all disabled, we don't want to find any checkboxes
    // that do not have a disabled state.
    $this->assertSession()->elementNotExists('css', 'input[type="checkbox"]:not([disabled])', $fieldUserPrisonsElement);
  }

  /**
   * Asserts that the user can make edits to the $node.
   *
   * @param NodeInterface $node
   *   Node to be tested.
   * @param bool $new_node
   *   Whether the node is newly created.
   *
   * @throws EntityMalformedException
   */
  protected function assertUserCanEditNode(NodeInterface $node, bool $new_node = TRUE) {
    $edit_url = $node->toUrl('edit-form');
    $this->visit($edit_url->toString());
    $this->assertUserCanEditNodeOnCurrentPage($node->getType(), $new_node);
  }

  /**
   * Asserts that the user can edit the node on the current page.
   *
   * Assumes the page is already in the current session.
   *
   * @param string $contentType
   *   The content type of the current page.
   * @param bool $new_node
   *   Whether the node is newly created.
   */
  protected function assertUserCanEditNodeOnCurrentPage(string $contentType, bool $new_node = TRUE) {
    // Test some fields are enabled, that appear on all content types.
    try {
      $this->assertSession()->fieldEnabled('title[0][value]');
      $entityType = $this->entityTypeManager->getDefinition('node');
      $publishedField = $this->moderationInformation->shouldModerateEntitiesOfBundle($entityType, $contentType) ? ($new_node ? 'Save as' : 'Change to') : 'Published';
      $this->assertSession()->fieldEnabled($publishedField);
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
