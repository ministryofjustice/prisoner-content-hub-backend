<?php

namespace Drupal\Tests\prisoner_hub_category_taxonomy\ExistingSiteJavascript;

use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\Entity\User;
use weitzman\DrupalTestTraits\ExistingSiteWebDriverTestBase;

/**
 * Javascript tests to ensure that the category and series fields are shown correctly.
 *
 * @group prisoner_hub_category_taxonomy
 */
class CategoryFieldTest extends ExistingSiteWebDriverTestBase {

  /**
   * The Studio Administrator user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $studioAdministrator;

  /**
   * The local content manager user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $localContentManagerUser;

  /**
   * The taxonomy term created with episode number sorting.
   *
   * @var \Drupal\taxonomy\Entity\Term
   */
  protected $episodeNumberTerm;

  /**
   * The taxonomy term created with date sorting.
   *
   * @var \Drupal\taxonomy\Entity\Term
   */
  protected $releaseDateTerm;

  protected static $contentTypes = ['moj_radio_item', 'page', 'moj_video_item', 'moj_pdf_item'];

  public function setUp() {
    parent::setUp();

    $this->studioAdministrator = User::create([
      'name' => 'test-studio-admin',
      'pass' => 'password',
      'roles' => ['local_administrator'],
      'status' => 1,
    ]);
    $this->studioAdministrator->save();
    $this->studioAdministrator->passRaw = 'password';

    $this->localContentManagerUser = User::create([
      'name' => 'test-local-content-manager',
      'pass' => 'password',
      'roles' => ['moj_local_content_manager'],
      'status' => 1,
    ]);
    $this->localContentManagerUser->save();
    $this->localContentManagerUser->passRaw = 'password';

    // Create some taxonomy terms to test with.
    // These will be automatically cleaned up at the end of the test.
    $series_vocab = Vocabulary::load('series');
    $this->createTerm($series_vocab);
    $categories_vocab = Vocabulary::load('moj_categories');
    $this->createTerm($categories_vocab);
  }

  /**
   * Test the correct fields appear when logged in as a studio admin.
   */
  public function testCategoryFieldsStudioAdmin() {
    $this->drupalLogin($this->studioAdministrator);
    foreach (self::$contentTypes as $contentType) {
      $this->checkCategoryFieldVisibility($contentType);
    }
  }

  /**
   * Test the correct fields appear when logged in as a local content manager.
   */
  public function testCategoryFieldsLocalContentManager() {
    $this->drupalLogin($this->localContentManagerUser);
    foreach (self::$contentTypes as $contentType) {
      $this->checkCategoryFieldVisibility($contentType);
    }
  }

  /**
   * Helper function to test a specific content type.
   *
   * @param string $content_type
   *   The content type machine name.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  private function checkCategoryFieldVisibility($content_type) {
    $this->visit('/node/add/' . $content_type);
    $web_assert = $this->assertSession();
    $web_assert->statusCodeEquals(200);
    $page = $this->getCurrentPage();
    $series_field = $page->findField('Series');
    $category_field = $page->findField('Category');
    self::assertFalse($category_field->isVisible());
    self::assertTrue($series_field->isVisible());

    $not_in_series_field = $page->findField('field_not_in_series[value]');
    $not_in_series_field->check();
    self::assertTrue($category_field->isVisible());
    self::assertFalse($series_field->isVisible());

  }

  /**
   * Remove the users we created for the test.
   */
  public function tearDown() {
    parent::tearDown();
    $this->studioAdministrator->delete();
    $this->localContentManagerUser->delete();
  }

}
