<?php

declare(strict_types=1);

namespace Drupal\Tests\prisoner_hub_protect_approved_content\ExistingSite;

use Drupal\node\NodeInterface;
use Drupal\Tests\prisoner_hub_test_traits\Traits\NodeCreationTrait;
use Drupal\user\UserInterface;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Test protected fields cannot be changed during certain workflow transitions.
 */
class ProtectFieldTest extends ExistingSiteBase {

  use NodeCreationTrait;

  /**
   * Test user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $localContentAuthor;

  /**
   * A map of content types to their protected fields.
   *
   * Each field is a tuple of the field name and a css locator used to locate
   * it on the editing form for that node.
   *
   * @var array[]
   */
  protected array $protectedFields;

  public function __construct() {
    parent::__construct();
    $this->protectedFields = [
      'page' => [
        ['field_main_body_content', '#edit-field-main-body-content-0-value'],
        ['field_moj_stand_first', '#edit-field-moj-stand-first-0-value'],
        // 'field_moj_thumbnail_image',
        ['field_summary', '#edit-field-summary-0-value'],
        ['title', '#edit-title-0-value'],
      ],
      'moj_pdf_item' => [
        // 'field_moj_pdf',
        // 'field_moj_thumbnail_image',
        ['field_summary', '#edit-field-summary-0-value'],
        ['title', '#edit-title-0-value'],
      ],
      'moj_radio_item' => [
        ['field_description', '#edit-field-description-0-value'],
        // 'field_moj_audio',
        // 'field_moj_thumbnail_image',
        ['field_summary', '#edit-field-summary-0-value'],
        ['title', '#edit-title-0-value'],
      ],
      'moj_video_item' => [
        ['field_description', '#edit-field-description-0-value'],
        // 'field_moj_thumbnail_image',
        // 'field_video',
        ['field_summary', '#edit-field-summary-0-value'],
        ['title', '#edit-title-0-value'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->localContentAuthor = $this->createUser();
    $this->localContentAuthor->addRole('moj_local_content_manager');
    $this->localContentAuthor->save();

    // Cause tests to fail if an error is sent to Drupal logs.
    $this->failOnLoggedErrors();
  }

  /**
   * Create a page to test editing.
   *
   * @param string $moderation_state
   *   Moderation state of the node.
   *
   * @return \Drupal\node\NodeInterface
   *   New page.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createPage(string $moderation_state): NodeInterface {
    $node = $this->createCategorisedNode([
      'title' => 'Original title',
      'moderation_state' => $moderation_state,
      'type' => 'page',
      'uid' => $this->localContentAuthor->id(),
      'field_main_body_content' => '<p>Original content</p>',
      'field_moj_stand_first' => 'Original stand first',
      'field_summary' => 'Original summary',
       // 'field_moj_thumbnail_image',
    ]);
    $node->save();

    return $node;
  }

  /**
   * Create a PDF to test editing.
   *
   * @param string $moderation_state
   *   Moderation state of the node.
   *
   * @return \Drupal\node\NodeInterface
   *   New PDF.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createPdf(string $moderation_state): NodeInterface {
    $node = $this->createCategorisedNode([
      'title' => 'Original title',
      'moderation_state' => $moderation_state,
      'type' => 'moj_pdf_item',
      'uid' => $this->localContentAuthor->id(),
      'field_summary' => 'Original summary',
      // 'field_moj_thumbnail_image',
      // 'field_moj_pdf',
    ]);
    $node->save();

    return $node;
  }

  /**
   * Create an audio to test editing.
   *
   * @param string $moderation_state
   *   Moderation state of the node.
   *
   * @return \Drupal\node\NodeInterface
   *   New audio.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createAudio(string $moderation_state): NodeInterface {
    $node = $this->createCategorisedNode([
      'title' => 'Original title',
      'moderation_state' => $moderation_state,
      'type' => 'moj_radio_item',
      'uid' => $this->localContentAuthor->id(),
      'field_description' => 'Original description',
      // 'field_moj_audio',
      // 'field_moj_thumbnail_image',
      'field_summary' => 'Original summary',
    ]);
    $node->save();

    return $node;
  }

  /**
   * Create an video to test editing.
   *
   * @param string $moderation_state
   *   Moderation state of the node.
   *
   * @return \Drupal\node\NodeInterface
   *   New video.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createVideo(string $moderation_state): NodeInterface {
    $node = $this->createCategorisedNode([
      'title' => 'Original title',
      'moderation_state' => $moderation_state,
      'type' => 'moj_video_item',
      'uid' => $this->localContentAuthor->id(),
      'field_description' => 'Original description',
      // 'field_video',
      // 'field_moj_thumbnail_image',
    ]);
    $node->save();

    return $node;
  }

  /**
   * Test protected fields throw errors when modified in protected transition.
   */
  public function testProtectedFieldsInProtectedTransition() {
    $this->testTransition('passed_review', 'published', TRUE);
  }

  /**
   * Test protected fields don't throw errors when changed in safe transitions.
   */
  public function testProtectedFieldsInUnprotectedTransition() {
    $this->testTransition('passed_review', 'draft', FALSE);
    $this->testTransition('passed_review', 'awaiting_review', FALSE);
  }

  /**
   * Test moving content through a specific transition.
   *
   * @param string $start_state
   *   Start state from the basic_editorial workflow.
   * @param string $end_state
   *   End state from the basic_editorial workflow.
   * @param bool $expect_validation_error
   *   TRUE - a successful test is when the transition raises validation errors.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function testTransition(string $start_state, string $end_state, bool $expect_validation_error) {
    $this->drupalLogin($this->localContentAuthor);

    foreach ($this->protectedFields as $node_type => $fields) {
      foreach ($fields as $field) {
        $node = match ($node_type) {
          'page' => $this->createPage($start_state),
          'moj_pdf_item' => $this->createPdf($start_state),
          'moj_radio_item' => $this->createAudio($start_state),
          'moj_video_item' => $this->createVideo($start_state),
        };
        $this->visit($node->toUrl('edit-form')->toString());
        $element = $this->assertSession()->elementExists('css', $field[1]);
        $element->setValue('New value');
        $moderationStateElement = $this->assertSession()->elementExists('css', '#edit-moderation-state-0-state');
        $moderationStateElement->setValue($end_state);
        $this->submitForm([], 'Save');
        if ($expect_validation_error) {
          $this->assertSession()->pageTextContains('You cannot change this field once this content has been approved for publishing.');
        }
        else {
          $this->assertSession()->pageTextNotContains('You cannot change this field once this content has been approved for publishing.');
        }
      }
    }
  }

}
