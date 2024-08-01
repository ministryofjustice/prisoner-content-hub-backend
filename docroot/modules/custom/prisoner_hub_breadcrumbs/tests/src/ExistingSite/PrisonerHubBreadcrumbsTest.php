<?php

namespace Drupal\Tests\prisoner_hub_breadcrumbs\ExistingSite;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Url;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\jsonapi\Functional\JsonApiRequestTestTrait;
use Drupal\Tests\prisoner_hub_test_traits\Traits\JsonApiTrait;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use weitzman\DrupalTestTraits\Entity\NodeCreationTrait;
use weitzman\DrupalTestTraits\Entity\TaxonomyCreationTrait;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Test the breadcrumb suggestions JSON:API resource works correctly.
 *
 * @group prisoner_hub_breadcrumbs
 */
class PrisonerHubBreadcrumbsTest extends ExistingSiteBase {

  use JsonApiRequestTestTrait;
  use JsonApiTrait;
  use NodeCreationTrait;
  use TaxonomyCreationTrait;

  /**
   * An array of category taxonomy term entities.
   *
   * @var array
   */
  protected $categoryTerms;

  /**
   * A generated series term, that is linked to $this->$categoryTerm.
   *
   * @var \Drupal\taxonomy\Entity\Term
   */
  protected $seriesTerm;

  /**
   * Set up content and taxonomy terms to test with.
   */
  protected function setUp(): void {
    parent::setUp();

    $vocab_categories = Vocabulary::load('moj_categories');
    $parentTerm = $this->createTerm($vocab_categories, [
      'name' => 'Parent category term',
    ]);
    $subCategoryTerm = $this->createTerm($vocab_categories, [
      'name' => 'Sub category term',
      'parent' => [
        ['target_id' => $parentTerm->id()],
      ],
    ]);
    $subSubCategoryTerm = $this->createTerm($vocab_categories, [
      'name' => 'Sub sub category term',
      'parent' => [
        ['target_id' => $subCategoryTerm->id()],
      ],
    ]);
    $this->createNode([
      'name' => 'Series term',
      'field_moj_top_level_categories' => [
        ['target_id' => $subSubCategoryTerm->id()],
      ],
      'field_not_in_series' => 1,
    ]);

    $this->categoryTerms = [$parentTerm, $subCategoryTerm, $subSubCategoryTerm];

    $vocab_series = Vocabulary::load('series');
    $this->seriesTerm = $this->createTerm($vocab_series, [
      'name' => 'Series term',
      'field_category' =>
        ['target_id' => $subSubCategoryTerm->id()],
    ]);
    $this->createNode([
      'field_moj_series' => [
        ['target_id' => $this->seriesTerm->id()],
      ],
      'field_not_in_series' => 0,
    ]);

    // Allow anonymous user to access entities without prison context.
    // As we're not testing the prison context part, this is unnecessary.
    // @todo Remove this when tests are refactored, and a single way of
    // creating entities (that includes adding relevant prisons) is used across
    // our tests.
    $role = Role::load(RoleInterface::ANONYMOUS_ID);
    $this->grantPermissions($role, ['view entity without prison context']);
  }

  /**
   * Test that a sub category has the correct breadrumbs.
   */
  public function testSubCategoryBreadcrumb() {
    // Use array_pop to remove the last category from the list (as this won't
    // appear in the breadcrumbs).
    $category = array_pop($this->categoryTerms);
    $this->assertJsonApiBreadcrumbResponse($category, $this->categoryTerms);
  }

  /**
   * Test that a series has the correct breadcrumbs.
   */
  public function testSeriesBreadcrumb() {
    // The series has been assigned to the sub-sub-category.
    // So all categories should appear in the breadcrumbs.
    $this->assertJsonApiBreadcrumbResponse($this->seriesTerm, $this->categoryTerms);
  }

  /**
   * Test content assigned to a category has the correct breadcrumbs.
   */
  public function testContentWithCategoryBreadcrumb() {
    $categories = $this->categoryTerms;
    $category = end($categories);
    $node = $this->createNode([
      'field_moj_top_level_categories' => [
        ['target_id' => $category->id()],
      ],
      'field_not_in_series' => 1,
    ]);
    $this->assertJsonApiBreadcrumbResponse($node, $this->categoryTerms);
  }

  /**
   * Test content assigned to a series has the corret breadcrumbs.
   */
  public function testContentWithSeriesBreadcrumb() {
    $node = $this->createNode([
      'field_moj_series' => [
        ['target_id' => $this->seriesTerm->id()],
      ],
      'field_not_in_series' => 0,
    ]);
    $breadcrumbs = $this->categoryTerms;
    $breadcrumbs[] = $this->seriesTerm;
    $this->assertJsonApiBreadcrumbResponse($node, $breadcrumbs);
  }

  /**
   * Test that a topic has the correct breadcrumbs.
   */
  public function testTopicsBreadcrumb() {
    $vocab = Vocabulary::load('topics');
    $term = $this->createTerm($vocab);
    $url = Url::fromUri('internal:/jsonapi/taxonomy_term/topics/' . $term->uuid());
    $response = $this->getJsonApiResponse($url);
    $this->assertSame(200, $response->getStatusCode(), $url->toString() . ' returns a 200 response.');
    $response_document = Json::decode((string) $response->getBody());
    $message = 'JSON response returns the correct results on url: ' . $url->toString();
    $expected_breadcrumbs = [
      [
        'uri' => '/',
        'title' => 'Home',
        'options' => [],
      ],
      [
        'uri' => '/topics',
        'title' => 'Browse all topics',
        'options' => [],
      ],
    ];
    $this->assertSame($expected_breadcrumbs, $response_document['data']['attributes']['breadcrumbs'], $message);
  }

  /**
   * Helper function to assert a jsonapi response returns the expected entities.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to check.
   * @param \Drupal\Core\Entity\ContentEntityInterface[] $breadcrumb_entities
   *   The expected breadcrumb entities.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  protected function assertJsonApiBreadcrumbResponse(ContentEntityInterface $entity, array $breadcrumb_entities) {
    $url = Url::fromUri('internal:/jsonapi/' . $entity->getEntityTypeId() . '/' . $entity->bundle() . '/' . $entity->uuid());
    $response = $this->getJsonApiResponse($url);
    $this->assertSame(200, $response->getStatusCode(), $url->toString() . ' returns a 200 response.');
    $response_document = Json::decode((string) $response->getBody());
    $message = 'JSON response returns the correct results on url: ' . $url->toString();

    if (empty($breadcrumb_entities)) {
      $this->assertEmpty($response_document['data']['attributes']['breadcrumbs'], $message);
    }
    else {
      $breadcrumbs = [
        [
          'uri' => Url::fromRoute('<front>')->toString(),
          'title' => 'Home',
          'options' => [],
        ],
      ];
      foreach ($breadcrumb_entities as $breadcrumb_entity) {
        $breadcrumbs[] = [
          'uri' => $breadcrumb_entity->toUrl()->toString(),
          'title' => $breadcrumb_entity->label(),
          'options' => [],
        ];
      }
      $this->assertEquals($breadcrumbs, $response_document['data']['attributes']['breadcrumbs'], $message);
    }
  }

}
