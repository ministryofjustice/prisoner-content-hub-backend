<?php

namespace Drupal\Tests\prisoner_hub_prison_context\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\jsonapi\Functional\JsonApiRequestTestTrait;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;
use GuzzleHttp\RequestOptions;

/**
 * Functional tests to ensure that prison context jsonapi paths work correctly.
 *
 * @group prisoner_hub_prison_context
 */
class PrisonContextTest extends BrowserTestBase {

  use JsonApiRequestTestTrait;
  use TaxonomyTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['jsonapi', 'prisoner_hub_prison_context'];

  /**
   * A user with permission to administer site configuration.
   *
   * @var \Drupal\taxonomy\Entity\Term
   */
  protected $term;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);
    $vocab = $this->createVocabulary();
    $this->term = $this->createTerm($vocab);
    /* @var \Drupal\Core\Routing\RouteBuilderInterface $route_builder */
    $route_builder = $this->container->get('router.builder');
    $route_builder->rebuildIfNeeded();
  }

  /**
   * Tests that prison context jsonapi routes have been setup.
   */
  public function testRoutes() {
    /* @var \Drupal\Core\Routing\RouteProviderInterface $route_provider */
    $route_provider = $this->container->get('router.route_provider');
    $this->assertGreaterThan(0, $route_provider->getRoutesByPattern('/jsonapi/prison/{prison}/node/article')->count(), 'There is a least one prison context jsonapi route.');
  }

  /**
   * Tests that prison context jsonapi routes return a valid response.
   */
  public function testResponse() {
    $machine_name = $this->term->get('machine_name')->getValue();

    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';

    $response = $this->request('GET', Url::fromUri('internal:/jsonapi/prison/' . $machine_name[0]['value'] . '/node/article'), $request_options);
    $this->assertSame(200, $response->getStatusCode());
  }
}
