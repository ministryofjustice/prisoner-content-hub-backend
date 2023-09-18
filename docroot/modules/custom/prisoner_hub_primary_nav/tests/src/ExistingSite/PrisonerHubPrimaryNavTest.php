<?php

namespace Drupal\Tests\prisoner_hub_primary_nav\ExistingSite;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\system\Entity\Menu;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\TermInterface;
use Drupal\Tests\jsonapi\Functional\JsonApiRequestTestTrait;
use GuzzleHttp\RequestOptions;
use weitzman\DrupalTestTraits\Entity\TaxonomyCreationTrait;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Test the primary nav JSON:API resource works correctly.
 *
 * @group prisoner_hub_primary_nav
 */
class PrisonerHubPrimaryNavTest extends ExistingSiteBase {

  use JsonApiRequestTestTrait;
  use TaxonomyCreationTrait;

  /**
   * Test the default menu is used for primary nav when none selected.
   */
  public function testDefaultPrimaryNav() {
    // Add some content to the default primary nav.
    $default_menu_name = $this->container->getParameter('prisoner_hub_primary_nav.default_menu');

    $menu_link_1 = MenuLinkContent::create([
      'title' => 'Test menu link 1',
      'menu_name' => $default_menu_name,
      'link' => ['uri' => 'internal:/test-1'],
      'weight' => 98,
    ]);
    $menu_link_1->save();
    $this->cleanupEntities[] = $menu_link_1;

    $menu_link_2 = MenuLinkContent::create([
      'title' => 'Test menu link 2',
      'menu_name' => $default_menu_name,
      'link' => ['uri' => 'https://www.example.com'],
      'weight' => 99,
    ]);
    $menu_link_2->save();
    $this->cleanupEntities[] = $menu_link_2;

    $vocab = Vocabulary::load('prisons');
    // Create a prison without a value for field_primary_navigation, so that
    // it picks up the default.
    $prison = $this->createTerm($vocab);
    $this->assertJsonApiSuggestionsResponse($prison, [
      $menu_link_1,
      $menu_link_2,
    ]);
  }

  /**
   * Test a specific menu is used when specified in field_primary_navigation.
   */
  public function testPrisonPrimaryNav() {
    $menu = Menu::create([
      'id' => 'test-prisoner-hub-primary-nav',
      'label' => 'Test primary nav menu (for automated tests)',
    ]);
    $menu->save();
    $this->cleanupEntities[] = $menu;

    $menu_link_1 = MenuLinkContent::create([
      'title' => 'Test prison menu link 1',
      'menu_name' => $menu->id(),
      'link' => ['uri' => 'internal:/test-prison-menu-1'],
      'weight' => 98,
    ]);
    $menu_link_1->save();
    $this->cleanupEntities[] = $menu_link_1;

    $menu_link_2 = MenuLinkContent::create([
      'title' => 'Test prison menu link 2',
      'menu_name' => $menu->id(),
      'link' => ['uri' => 'https://www.example2.com'],
      'weight' => 99,
    ]);
    $menu_link_2->save();
    $this->cleanupEntities[] = $menu_link_2;

    $vocab = Vocabulary::load('prisons');
    $prison = $this->createTerm($vocab, [
      'field_primary_navigation' => [
        ['target_id' => $menu->id()],
      ],
    ]);
    $this->assertJsonApiSuggestionsResponse($prison, [
      $menu_link_1,
      $menu_link_2,
    ]);
  }

  /**
   * Helper function to assert a response returns the expected menu items.
   *
   * @param \Drupal\taxonomy\TermInterface $term
   *   Term used to construct the JSON:API we are testing.
   * @param \Drupal\Core\Menu\MenuLinkInterface[] $menu_items_to_check
   *   A list of menu links to check for in the JSON response.
   */
  protected function assertJsonApiSuggestionsResponse(TermInterface $term, array $menu_items_to_check) {
    $url = Url::fromUri('internal:/jsonapi/prison/' . $term->get('machine_name')->getValue()[0]['value'] . '/primary_navigation');
    $response = $this->getJsonApiResponse($url);
    $this->assertSame(200, $response->getStatusCode(), $url->toString() . ' returns a 200 response.');
    $response_document = Json::decode((string) $response->getBody());
    $message = 'JSON response returns the correct results on url: ' . $url->toString();
    if (empty($menu_items_to_check)) {
      $this->assertEmpty($response_document['data'], $message);
    }
    else {
      /** @var \Drupal\Core\Menu\MenuLinkInterface $menu_item */
      foreach (array_reverse($menu_items_to_check) as $menu_item) {
        // We run the check from the last breadcrumb and work backwards,
        // this allows us to use a menu with existing links in.
        $last_menu_item_in_response = array_pop($response_document['data']);
        $this->assertSame($menu_item->getTitle(), $last_menu_item_in_response['attributes']['title']);
        $this->assertSame($menu_item->getUrlObject()->toString(), $last_menu_item_in_response['attributes']['url']);
      }
    }
  }

  /**
   * Get a response from a JSON:API url.
   *
   * @param \Drupal\Core\Url $url
   *   The url object to use for the JSON:API request.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The response object.
   */
  public function getJsonApiResponse(Url $url) {
    $request_options = [];
    $request_options[RequestOptions::HEADERS]['Accept'] = 'application/vnd.api+json';
    return $this->request('GET', $url, $request_options);
  }

}
