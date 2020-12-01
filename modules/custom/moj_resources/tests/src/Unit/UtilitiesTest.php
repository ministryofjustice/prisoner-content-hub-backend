<?php

namespace Drupal\Tests\moj_resources\Unit;

use Drupal\moj_resources\Utilities;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Tests\UnitTestCase;
use Drupal\Tests\moj_resources\Unit\Supports\AudioContent;
use Drupal\Tests\moj_resources\Unit\Supports\TestHelpers;
use Drupal\Tests\moj_resources\Unit\Supports\VideoContent;

/**
 * MOJ Resources Utilities
 *
 * @group unit_moj_resources
 */

class UtilitiesTest extends UnitTestCase
{
  public $entityQueryFactory;

  public function setUp() {
      $this->entityQueryFactory = $this->getMockBuilder('Drupal\Core\Entity\Query\QueryFactory')
        ->disableOriginalConstructor()
        ->setMethods(array('get', 'condition','andConditionGroup', 'orConditionGroup', 'notExists', 'exists'))
        ->getMock();

      $this->entityQueryFactory->expects($this->any())
        ->method($this->anything())
        ->will($this->returnSelf());
  }

  /*
  * Test filter by prison
  *
  * @return void
  */
  public function testFilterByPrisonCategories() {
    $this->entityQueryFactory->expects($this->atLeastOnce())
      ->method('andConditionGroup');

      $this->entityQueryFactory->expects($this->once())
      ->method('orConditionGroup');

    $this->entityQueryFactory->expects($this->atLeastOnce())
      ->method('condition')
      ->withConsecutive(
        array('field_moj_prisons', 123, '='),
        array('field_prison_categories', [456], 'IN')
      );

    $this->entityQueryFactory->expects($this->atLeastOnce())
      ->method('exists')
      ->withConsecutive(
        array('field_moj_prisons'),
        array('field_prison_categories')
      );

    $query = Utilities::filterByPrisonCategories(123, [456], $this->entityQueryFactory->get('node'));

    $this->assertInstanceOf('Drupal\Core\Entity\Query\QueryFactory', $query);
  }

  /*
  * Test get prisons for a node
  *
  * @return void
  */
  public function testGetPrisonsFor() {
    $tests = array(
      array(123, 456),
      array(123),
      array(123, 456, 789),
      array()
    );

    foreach ($tests as $testData) {
      $testContent = AudioContent::createWithNodeId($this, 123);

      foreach ($testData as $prisonId) {
        $testContent->addPrison($prisonId);
      }

      $node = TestHelpers::createMockNode($this, $testContent);

      $prisons = Utilities::getPrisonsFor($node);
      $this->assertEquals(count($prisons), count($testData));
      $this->assertEquals($prisons, $testData);
    }
  }

  /*
  * Test get prison categories for a node
  *
  * @return void
  */
  public function testGetPrisonCategoriesFor() {
    $tests = array(
      array(123, 456),
      array(123),
      array(123, 456, 789),
      array()
    );

    foreach ($tests as $testData) {
      $testContent = AudioContent::createWithNodeId($this, 123);

      foreach ($testData as $prisonCategoryId) {
        $testContent->addPrisonCategory($prisonCategoryId);
      }

      $node = TestHelpers::createMockNode($this, $testContent);

      if (empty($testData)) {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\BadRequestHttpException::class);
        $prisonCategories = Utilities::getPrisonCategoriesFor($node);
      } else {
        $prisonCategories = Utilities::getPrisonCategoriesFor($node);
        $this->assertEquals(count($prisonCategories), count($testData));
        $this->assertEquals($prisonCategories, $testData);
      }
    }
  }
}
