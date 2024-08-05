<?php

namespace Drupal\Tests\prisoner_hub_priority_content;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\prisoner_hub_test_traits\Traits\JsonApiTrait;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Tests for priority content.
 *
 * @group prisoner_hub_priority_content
 */
class PriorityContentTest extends ExistingSiteBase {

  use NodeCreationTrait;
  use JsonApiTrait;

  /**
   * User on the comms team.
   */
  protected AccountInterface $commsUser;

  /**
   * User not on the comms team.
   */
  protected AccountInterface $nonCommsUser;

  /**
   * Admin user.
   */
  protected AccountInterface $adminUser;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function setUp(): void {
    parent::setUp();

    $this->commsUser = $this->createUser();
    $this->commsUser->addRole('comms_live_service_hq');
    $this->commsUser->addRole('local_administrator');
    $this->commsUser->save();

    $this->nonCommsUser = $this->createUser();
    $this->nonCommsUser->addRole('local_administrator');
    $this->nonCommsUser->save();

    $this->adminUser = $this->createUser();
    $this->adminUser->addRole('administrator');
    $this->adminUser->save();
  }

  /**
   * Tests comms team can see flag to prioritise content, defaulting to on.
   */
  public function testCommsTeamCanPrioritiseContent(): void {
    $this->drupalLogin($this->commsUser);
    $this->visit('/node/add/page');
    $this->assertTrue($this->getCurrentPage()->hasField('Prioritise on Recently Added section of home pages'));

    // Check comms team defaults to being prioritised.
    $this->assertEquals(1, $this->getCurrentPage()->findField('Prioritise on Recently Added section of home pages')->getValue());
    $this->drupalLogout();
  }

  /**
   * Tests non-comms team cannot see flag to prioritise content.
   */
  public function testNonCommsCannotPrioritiseContent(): void {
    $this->drupalLogin($this->nonCommsUser);
    $this->visit('/node/add/page');
    $this->assertFalse($this->getCurrentPage()->hasField('Prioritise on Recently Added section of home pages'));
    $this->drupalLogout();
  }

  /**
   * Tests admin can see the flag, but it defaults to off.
   */
  public function testAdminCanPrioritiseContent(): void {
    $this->drupalLogin($this->adminUser);
    $this->visit('/node/add/page');
    $this->assertTrue($this->getCurrentPage()->hasField('Prioritise on Recently Added section of home pages'));

    // Check admin defaults to not being prioritised.
    $this->assertEquals(0, $this->getCurrentPage()->findField('Prioritise on Recently Added section of home pages')->getValue());
    $this->drupalLogout();
  }

  /**
   * Test comms can see the flag for content created by other users.
   *
   * It should not be set.
   */
  public function testCommsSeeFlagOnOthersContent(): void {
    $this->drupalLogin($this->nonCommsUser);
    $node = $this->createNode();
    $this->drupalLogout();

    $this->drupalLogin($this->commsUser);
    $this->visit('/node/' . $node->id() . '/edit');
    $this->assertEquals(0, $this->getCurrentPage()->findField('Prioritise on Recently Added section of home pages')->getValue());
    $this->drupalLogout();
  }

  /**
   * Test prioritised content is returned first in the recently added endpoint.
   *
   * Also tests that the prioritised content is no more than 50% of the
   * returned content, even when more is available.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testPrioritisedContentIsPrioritised(): void {
    // Create 4 priority nodes.
    for ($index = 1; $index < 5; $index++) {
      $node = $this->createNode([
        'title' => "Priority Node $index",
        'field_prioritise_on_recently_add' => 1,
      ]);
      $node->save();
    }

    // Create 4 non-priority nodes.
    for ($index = 1; $index < 5; $index++) {
      $node = $this->createNode([
        'title' => "Non-Priority Node $index",
        'field_prioritise_on_recently_add' => 0,
      ]);
      $node->save();
    }

    $url = Url::fromUri('internal:/jsonapi/recently-added?page[limit]=4');
    $response = $this->getJsonApiResponse($url);
    $response_document = Json::decode((string) $response->getBody());
    $this->assertEquals(4, count($response_document['data']));
    // Check first two data elements are priority content, and second two are
    // not.
    $this->assertStringStartsWith('Priority Node ', $response_document['data'][0]['attributes']['title']);
    $this->assertStringStartsWith('Priority Node ', $response_document['data'][1]['attributes']['title']);
    $this->assertStringStartsWith('Non-Priority Node ', $response_document['data'][2]['attributes']['title']);
    $this->assertStringStartsWith('Non-Priority Node ', $response_document['data'][3]['attributes']['title']);
  }

}
