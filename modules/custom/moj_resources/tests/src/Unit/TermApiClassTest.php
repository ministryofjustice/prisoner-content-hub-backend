<?php

namespace Drupal\Tests\moj_resources\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\Tests\moj_resources\Unit\Supports\TestHelpers;
use Drupal\Tests\moj_resources\Unit\Supports\VideoContent;
use Drupal\Tests\moj_resources\Unit\Supports\AudioContent;
use Drupal\Tests\moj_resources\Unit\Supports\Prison;
use Drupal\Tests\moj_resources\Unit\Supports\Series;
use Drupal\moj_resources\TermApiClass;

/**
 * Term API Unit tests
 *
 * @group unit_moj_resources
 */
class TermApiClassTest extends UnitTestCase
{
    public function testReturnsSeries() {
      $testSeries = Series::createWithNodeId($this, 456)
        ->setPromotedPrison(123)
        ->addPrisonCategory(1234);

      $testPrison = Prison::createWithNodeId($this, 123)
        ->addPrisonCategory(1234);

      $series = TestHelpers::createMockNode($this, $testSeries);
      $prison = TestHelpers::createMockNode($this, $testPrison);

      $termStorage = TestHelpers::createMockNodeStorage($this, 'load', array(
        array(456, $series),
        array(123, $prison)
      ));

      $entityManager = TestHelpers::createMockEntityManager($this, array(
        array('taxonomy_term', $termStorage)
      ));

      $termApiClass = new TermApiClass($entityManager);

      $term = $termApiClass->termApiEndpoint(456, 123);

      $this->assertEquals($term['id'], 456);
      $this->assertEquals($term['content_type'], 'series');
      $this->assertEquals($term['title'], 'Test Term');
      $this->assertEquals($term['description']['processed'], 'This is a test term');
      $this->assertEquals($term['summary'], 'Content summary');
      $this->assertEquals($term['programme_code'], 'AB123');
      $this->assertEquals($term['image']['url'], '/foo.jpg');
      $this->assertEquals($term['video']['url'], '/foo.mp4');
      $this->assertEquals($term['audio']['url'], '/foo.mp3');

      $this->assertEquals(count($term['prison_categories']), 1);
      $this->assertEquals($term['prison_categories'][0], 1234);
    }
}
