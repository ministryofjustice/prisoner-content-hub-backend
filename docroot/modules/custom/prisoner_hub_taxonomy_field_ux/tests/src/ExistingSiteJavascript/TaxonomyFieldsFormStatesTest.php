<?php

namespace Drupal\Tests\prisoner_hub_taxonomy_field_ux\ExistingSiteJavascript;

use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\Entity\User;
use weitzman\DrupalTestTraits\ExistingSiteSelenium2DriverTestBase;

/**
 * Functional tests to ensure that series sorting fields work correctly.
 *
 * @group prisoner_hub_taxonomy_field_ux
 */
class TaxonomyFieldsFormStatesTest extends ExistingSiteSelenium2DriverTestBase {

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

  /**
   * Content types to test on for studio admins.
   *
   * @var string[]
   */
  protected static $studioAdminContentTypes = ['moj_radio_item', 'page', 'moj_video_item', 'moj_pdf_item', 'link'];

  /**
   * Content types to test on for local content managers.
   *
   * @var string[]
   */
  protected static $localContentManagerContentTypes = ['moj_radio_item', 'page', 'moj_video_item', 'moj_pdf_item'];

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

    // Create taxonomy terms with field_sort_by values.
    // These will be automatically cleaned up at the end of the test.
    $vocab = Vocabulary::load('series');
    $this->episodeNumberTerm = $this->createTerm($vocab, ['name' => 'Series 1', 'field_sort_by' => 'season_and_episode_asc']);
    $this->releaseDateTerm = $this->createTerm($vocab, ['name' => 'Series 2', 'field_sort_by' => 'release_date_desc']);
  }

  /**
   * Test the correct series sorting fields appear when logged in as a studio admin.
   */
  public function testSeriesSortingFieldsStudioAdmin() {
    $this->drupalLogin($this->studioAdministrator);
    foreach (self::$studioAdminContentTypes as $contentType) {
      $this->checkSortingFieldVisibility($contentType);
    }
  }

  /**
   * Test that the category field appears when logged in as a studio admin.
   */
  public function testCategoryFieldStudioAdmin() {
    $this->drupalLogin($this->studioAdministrator);
    foreach (self::$studioAdminContentTypes as $contentType) {
      $this->checkCategoryFieldVisibility($contentType);
    }
  }

  /**
   * Test the correct series sorting fields appear when logged in as a local content mamager..
   */
  public function testSeriesSortingFieldsLocalContentManager() {
    $this->drupalLogin($this->localContentManagerUser);
    foreach (self::$localContentManagerContentTypes as $contentType) {
      $this->checkSortingFieldVisibility($contentType);
    }
  }

  /**
   * Test that the category field appears when logged in as a local content manager.
   */
  public function testCategoryFieldLocalContentManager() {
    $this->drupalLogin($this->localContentManagerUser);
    foreach (self::$localContentManagerContentTypes as $contentType) {
      $this->checkCategoryFieldVisibility($contentType);
    }
  }

  /**
   * Helper function to test series field visibility.
   *
   * @param string $content_type
   *   The content type machine name.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  private function checkSortingFieldVisibility($content_type) {
    $this->visit('/node/add/' . $content_type);
    $web_assert = $this->assertSession();
    $web_assert->statusCodeEquals(200);
    $page = $this->getCurrentPage();
    $season_field = $page->findField('Season');
    $episode_field = $page->findField('Episode');
    $release_date_field = $page->findField('Date');
    self::assertFalse($season_field->isVisible());
    self::assertFalse($episode_field->isVisible());
    self::assertFalse($release_date_field->isVisible());

    $series_field = $page->findField('Series');
    $series_field->setValue($this->episodeNumberTerm->id());
    self::assertTrue($season_field->isVisible());
    self::assertTrue($episode_field->isVisible());
    self::assertFalse($release_date_field->isVisible());

    $series_field->setValue($this->releaseDateTerm->id());
    self::assertFalse($season_field->isVisible());
    self::assertFalse($episode_field->isVisible());
    self::assertTrue($release_date_field->isVisible());
  }

  /**
   * Helper function to test category field visibility.
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
    $category_field = $page->findField('Category');
    self::assertFalse($category_field->isVisible());

    $not_in_series_field = $page->findField('This content is not part of any series');
    $not_in_series_field->check();
    self::assertTrue($category_field->isVisible());
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
