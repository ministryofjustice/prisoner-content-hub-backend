<?php

namespace Drupal\Tests\prisoner_hub_taxonomy_sorting\ExistingSiteJavascript;

use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\Entity\User;
use weitzman\DrupalTestTraits\ExistingSiteWebDriverTestBase;

/**
 * Functional tests to ensure that prison context jsonapi paths work correctly.
 *
 * @group prisoner_hub_prison_context
 */
class PrisonContextTest extends ExistingSiteWebDriverTestBase {

  public function setUp() {
    parent::setUp();

    $user = User::create([
      'name' => 'test-studio-manager',
      'pass' => 'password',
      'roles' => ['local_administrator'],
      'status' => 1,
    ]);
    $user->save();
  }

  public function testContentCreation() {
    // Create a taxonomy term. Will be automatically cleaned up at the end of the test.
    $web_assert = $this->assertSession();
    $vocab = Vocabulary::load('series');
    $this->createTerm($vocab, ['name' => 'Series 1', 'field_sort_by' => 'season_and_episode_asc']);
    $this->createTerm($vocab, ['name' => 'Series 2', 'field_sort_by' => 'release_date_desc']);

    $this->visit('/user/login');
    $web_assert = $this->assertSession();
    $web_assert->statusCodeEquals(200);


    $page = $this->getCurrentPage();
    $page->fillField('name', 'test-studio-manager');
    $page->fillField('pass', 'password');
    $submit_button = $page->findButton('Log in');
    $submit_button->press();
    $web_assert->statusCodeEquals(200);
    $content = $this->getCurrentPageContent();
    $stop = 1;

    $this->visit('/node/add/moj_radio_item');
    $web_assert = $this->assertSession();
    $web_assert->statusCodeEquals(200);

  }

}
