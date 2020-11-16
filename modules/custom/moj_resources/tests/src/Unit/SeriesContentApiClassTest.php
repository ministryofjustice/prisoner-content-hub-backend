<?php

namespace Drupal\Tests\moj_resources\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\Tests\moj_resources\Unit\Supports\TestHelpers;
use Drupal\Tests\moj_resources\Unit\Supports\VideoContent;
use Drupal\Tests\moj_resources\Unit\Supports\AudioContent;
use Drupal\Tests\moj_resources\Unit\Supports\Prison;
use Drupal\Tests\moj_resources\Unit\Supports\Series;
use Drupal\moj_resources\SeriesContentApiClass;

/**
 * Series Content API Unit tests
 *
 * @group unit_moj_resources
 */
class SeriesContentApiClassTest extends UnitTestCase
{
    public function testGetSeriesContentVideoFormat() {
      $testContent = VideoContent::createWithNodeId(123)
        ->setSeason(1)
        ->setEpisode(1)
        ->addPrison(123)
        ->addSeries(456)
        ->addSecondaryTag(789)
        ->addCategory(123);

      $testSeries = Series::createWithNodeId(456)
        ->addPrisonCategory(1234);

      $testPrison = Prison::createWithNodeId(123)
        ->addPrisonCategory(1234);

      $node = TestHelpers::createMockNode($this, $testContent);
      $series = TestHelpers::createMockNode($this, $testSeries);
      $prison = TestHelpers::createMockNode($this, $testPrison);

      $nodeStorage = TestHelpers::createMockNodeStorage($this, 'loadMultiple', array(
        array(array(1234 => 1234), array($node))
      ));

      $termStorage = TestHelpers::createMockNodeStorage($this, 'load', array(
        array(456, $series),
        array(123, $prison)
      ));

      $entityManager = TestHelpers::createMockEntityManager($this, array( // Refactor this to return different NodeStorage objects
          array("node", $nodeStorage),
          array("taxonomy_term", $termStorage)
      ));
      $entityQueryFactory = TestHelpers::createMockQueryFactory($this, array(1234 => 1234));

      $seriesContentApiClass = new SeriesContentApiClass($entityManager, $entityQueryFactory);

      $series = $seriesContentApiClass->SeriesContentApiEndpoint(
        "en/GB",
        456,
        null,
        null,
        123,
        "ASC"
      );

      $this->assertEquals(count($series), 1);
      $content = $series[0];
      $this->assertEquals($content["id"], 123);
      $this->assertEquals($content["content_type"], "moj_video_item");
      $this->assertEquals($content["title"], "Test Content");
      $this->assertEquals($content["season"], 1);
      $this->assertEquals($content["episode"], 1);
      $this->assertEquals($content["episode_id"], 1001);
      $this->assertEquals($content["duration"], 60);
      $this->assertEquals($content["media"]->url, "/foo.mp4");
      $this->assertEquals($content["image"]->url, "/foo.jpg");
      $this->assertEquals(count($content["secondary_tags"]), 1);
      $this->assertEquals($content["secondary_tags"][0]->target_id, 789);
      $this->assertEquals(count($content["categories"]), 1);
      $this->assertEquals($content["categories"][0]->target_id, 123);
    }

    public function testGetSeriesContentAudioFormat() {
      $testContent = AudioContent::createWithNodeId(123)
        ->setSeason(1)
        ->setEpisode(1)
        ->addPrison(123)
        ->addSeries(456)
        ->addSecondaryTag(789)
        ->addCategory(123);

      $testSeries = Series::createWithNodeId(456)
        ->addPrisonCategory(1234);

      $testPrison = Prison::createWithNodeId(123)
        ->addPrisonCategory(1234);

      $node = TestHelpers::createMockNode($this, $testContent);
      $series = TestHelpers::createMockNode($this, $testSeries);
      $prison = TestHelpers::createMockNode($this, $testPrison);

      $nodeStorage = TestHelpers::createMockNodeStorage($this, 'loadMultiple', array(
        array(array(1234 => 1234), array($node))
      ));

      $termStorage = TestHelpers::createMockNodeStorage($this, 'load', array(
        array(456, $series),
        array(123, $prison)
      ));

      $entityManager = TestHelpers::createMockEntityManager($this, array( // Refactor this to return different NodeStorage objects
        array("node", $nodeStorage),
        array("taxonomy_term", $termStorage)
      ));

      $entityQueryFactory = TestHelpers::createMockQueryFactory($this, array(1234 => 1234));

      $seriesContentApiClass = new SeriesContentApiClass($entityManager, $entityQueryFactory);

      $series = $seriesContentApiClass->SeriesContentApiEndpoint(
        "en/GB",
        456,
        null,
        null,
        123,
        "ASC"
      );

      $this->assertEquals(count($series), 1);
      $content = $series[0];
      $this->assertEquals($content["id"], 123);
      $this->assertEquals($content["content_type"], "moj_radio_item");
      $this->assertEquals($content["title"], "Test Content");
      $this->assertEquals($content["season"], 1);
      $this->assertEquals($content["episode"], 1);
      $this->assertEquals($content["episode_id"], 1001);
      $this->assertEquals($content["duration"], 60);
      $this->assertEquals($content["media"]->url, "/foo.mp3");
      $this->assertEquals($content["image"]->url, "/foo.jpg");
      $this->assertEquals(count($content["secondary_tags"]), 1);
      $this->assertEquals($content["secondary_tags"][0]->target_id, 789);
      $this->assertEquals(count($content["categories"]), 1);
      $this->assertEquals($content["categories"][0]->target_id, 123);
    }
}
