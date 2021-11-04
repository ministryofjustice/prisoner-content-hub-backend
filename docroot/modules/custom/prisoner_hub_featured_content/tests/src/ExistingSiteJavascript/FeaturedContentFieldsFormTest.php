<?php

namespace Drupal\Tests\prisoner_hub_featured_content\ExistingSiteJavascript;

use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\Entity\User;
use weitzman\DrupalTestTraits\ExistingSiteWebDriverTestBase;

/**
 * Functional tests to verify that the featured content fields work correctly.
 *
 * @group prisoner_hub_prison_featured_content
 */
class FeaturedContentFieldsFormTest extends ExistingSiteWebDriverTestBase {

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
   * The category taxonomy term created with an associated series.
   *
   * @var \Drupal\taxonomy\Entity\Term
   */
  protected $categoryTermForSeries;

  /**
   * The category taxonomy term created without an associated series.
   *
   * @var \Drupal\taxonomy\Entity\Term
   */
  protected $categoryTerm;

  /**
   * The series taxonomy term created with associated category.
   *
   * @var \Drupal\taxonomy\Entity\Term
   */
  protected $seriesWithCategoryTerm;

  /**
   * The series taxonomy term created without and associated category.
   *
   * @var \Drupal\taxonomy\Entity\Term
   */
  protected $seriesWithoutCategoryTerm;

  /**
   * Content types to test on.  These should all contain the category and
   * series field.
   *
   * @var string[]
   */
  protected static $contentTypes = ['moj_radio_item', 'page', 'moj_video_item', 'moj_pdf_item'];

  /**
   * Create users and taxonomy terms to test with.
   *
   * This has mostly been copied over from
   * Drupal\Tests\prisoner_hub_taxonomy_sorting\ExistingSiteJavascript/SeriesFieldsFormTest
   * @TODO: Move this all into a base class.
   */
  public function setUp(): void {
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

    // Create categories.
    $categories_vocab = Vocabulary::load('moj_categories');
    $this->categoryTerm = $this->createTerm($categories_vocab);
    $this->categoryTermForSeries = $this->createTerm($categories_vocab);

    // Create taxonomy terms with field_sort_by values.
    // These will be automatically cleaned up at the end of the test.
    $series_vocab = Vocabulary::load('series');
    $this->seriesWithCategoryTerm = $this->createTerm($series_vocab, ['name' => 'Series 1', 'field_category' => ['target_id' => $this->categoryTermForSeries->id()]]);
    $this->seriesWithoutCategoryTerm = $this->createTerm($series_vocab, ['name' => 'Series 2']);

    foreach (self::$contentTypes as $contentType) {
      $values = [
        'type' => $contentType,
        'uid' => $this->localContentManagerUser->id(),
      ];
      $this->nodes['category'][] = $this->createNode(array_merge($values, [
        'field_moj_top_level_categories' => ['target_id' => $this->categoryTerm->id()],
        'field_not_in_series' => 1,
      ]));
      $this->nodes['series'][] = $this->createNode(array_merge($values, [
        'field_moj_series' => ['target_id' => $this->seriesWithCategoryTerm->id()],
        'field_not_in_series' => 0,
      ]));
    }
  }

  /**
   * Test the correct fields appear when logged in as a studio admin.
   */
  public function testFeatuerdContentFieldsStudioAdmin() {
    $this->drupalLogin($this->studioAdministrator);
    foreach (self::$contentTypes as $contentType) {
      $this->testFeaturedContentFieldVisibilityNewContent($contentType);
    }
    $this->testFeaturedContentFieldVisibilityExistingContent();
  }

  /**
   * Test the correct fields appear when logged in as a local content manager.
   */
  public function testFeaturedContentFieldsLocalContentManager() {
    $this->drupalLogin($this->localContentManagerUser);
    foreach (self::$contentTypes as $contentType) {
      $this->testFeaturedContentFieldVisibilityNewContent($contentType);
    }
    $this->testFeaturedContentFieldVisibilityExistingContent();
  }

  /**
   * Helper function to test a specific content type.
   *
   * @param string $content_type
   *   The content type machine name.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  private function testFeaturedContentFieldVisibilityNewContent($content_type) {
    $this->visit('/node/add/' . $content_type);
    $web_assert = $this->assertSession();
    $web_assert->statusCodeEquals(200);
    $page = $this->getCurrentPage();

    $series_field = $page->findField('Series');
    $series_field->setValue($this->seriesWithCategoryTerm->id());

    $feature_on_category_field = $page->findById('edit-field-feature-on-category-wrapper')->findField($this->categoryTermForSeries->label());
    self::assertTrue($feature_on_category_field->isVisible());

    $series_field->setValue($this->seriesWithoutCategoryTerm->id());
    self::assertFalse($feature_on_category_field->isVisible());

    $not_in_series_field = $page->findField('This content is not part of any series');
    $not_in_series_field->check();
    $category_field = $page->findField('Category');
    $category_field->setValue($this->categoryTerm->id());
    $feature_on_category_field = $page->findById('edit-field-feature-on-category-wrapper')->findField($this->categoryTerm->label());
    self::assertTrue($feature_on_category_field->isVisible());
  }


  /**
   * Helper function to test on existing content.
   */
  private function testFeaturedContentFieldVisibilityExistingContent() {
    /** @var \Drupal\node\NodeInterface $node */
    foreach ($this->nodes as $category_or_series => $nodes) {
      foreach ($nodes as $node) {
        $this->visit('/node/' . $node->id() . '/edit');
        $web_assert = $this->assertSession();
        $web_assert->statusCodeEquals(200);
        $feature_on_category_field_wrapper = $this->getCurrentPage()->findById('edit-field-feature-on-category-wrapper');
        if ($category_or_series == 'category') {
          $feature_on_category_field = $feature_on_category_field_wrapper->findField($this->categoryTerm->label());
        }
        else {
          $feature_on_category_field = $feature_on_category_field_wrapper->findField($this->categoryTermForSeries->label());
        }
        if (!$feature_on_category_field->isVisible()) {
          print $node->id(); exit;
        }
        self::assertTrue($feature_on_category_field->isVisible());
      }
    }
  }

  /**
   * Remove the users we created for the test (this isn't handled by the parent
   * class).
   */
  public function tearDown(): void {
    parent::tearDown();
    $this->studioAdministrator->delete();
    $this->localContentManagerUser->delete();
  }

}
