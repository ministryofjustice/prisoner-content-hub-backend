<?php

namespace Drupal\Tests\moj_resources\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\moj_resources\Utilities;

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
        ->setMethods(array('get', 'condition', 'orConditionGroup', 'notExists'))
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
  public function testFilterByPrison() {
    $this->entityQueryFactory->expects($this->once())
      ->method('orConditionGroup');

    $this->entityQueryFactory->expects($this->atLeastOnce())
      ->method('condition');

    $query = Utilities::filterByPrison(123, $this->entityQueryFactory->get('node'));

    $this->assertInstanceOf('Drupal\Core\Entity\Query\QueryFactory', $query);
  }

  /*
  * Test filter by prison categories
  *
  * @return void
  */
  public function testFilterByPrisonCategories() {
    $this->entityQueryFactory->expects($this->once())
      ->method('orConditionGroup');

    $this->entityQueryFactory->expects($this->atLeastOnce())
      ->method('condition');

    $query = Utilities::filterByPrisonCategories(123, $this->entityQueryFactory->get('node'));

    $this->assertInstanceOf('Drupal\Core\Entity\Query\QueryFactory', $query);
  }
}
