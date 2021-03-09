<?php

namespace Drupal\Tests\prisoner_hub_prison_context\ExistingSite;

use Drupal\taxonomy\Entity\Vocabulary;
use weitzman\DrupalTestTraits\Entity\TaxonomyCreationTrait;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Functional tests to ensure that prison context jsonapi paths work correctly.
 *
 * @group prisoner_hub_prison_context
 */
class PrisonContextTest extends ExistingSiteBase {

  use TaxonomyCreationTrait;

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
    $vocab = Vocabulary::load('prisons');

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

}
