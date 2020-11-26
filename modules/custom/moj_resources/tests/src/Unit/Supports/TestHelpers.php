<?php

namespace Drupal\Tests\moj_resources\Unit\Supports;

use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\moj_resources\SeriesContentApiClass;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\moj_resources\Unit\Supports\Content;
use Drupal\Tests\moj_resources\Unit\Supports\Term;
use Drupal\Tests\UnitTestCase;


/**
 * Test Helpers for Unit tests
 *
 * @group unit_moj_resources
 */

class TestHelpers {
  public $unitTestCase;

  public function __construct($unitTestCase) {
    $this->unitTestCase = $unitTestCase;
  }

  /**
   * Create a mock node
   *
   * @param UnitTestCase $unitTestCase
   * @param Content|Term $content
   * @return object
  */
  public static function createMockNode($unitTestCase, $content) {
    $node = $unitTestCase->getMockBuilder('Drupal\node\Entity\Node')
      ->disableOriginalConstructor()
      ->getMock();

    $node->expects($unitTestCase->any())
      ->method('access')
      ->willReturn(true);

    $contentReturnValues = $content->createReturnValueMap();

    $node->expects($unitTestCase->any())
      ->method("__get")
      ->will($unitTestCase->returnValueMap($contentReturnValues));

    $node->expects($unitTestCase->any())
      ->method("get")
      ->will($unitTestCase->returnValueMap($contentReturnValues));

    return $node;
  }

  /**
   * Create a mock QueryFactory which returns an array of Node IDs
   *
   * @param UnitTestCase $unitTestCase
   * @param int[] $nodeIdsToReturn
   * @return object
  */
  public static function createMockQueryFactory($unitTestCase, $nodeIdsToReturn) {
    $queryFactory = $unitTestCase->getMockBuilder('Drupal\Core\Entity\Query\QueryFactory')
      ->disableOriginalConstructor()
      ->setMethods(array('get', 'condition', 'sort', 'range', 'execute', 'accessCheck', 'orConditionGroup', 'notExists', 'andConditionGroup'))
      ->getMock();

    $queryFactory->expects($unitTestCase->any())
      ->method('execute')
      ->will($unitTestCase->returnValue($nodeIdsToReturn));

    $queryFactory->expects($unitTestCase->any())
      ->method($unitTestCase->anything())
      ->will($unitTestCase->returnSelf());

    return $queryFactory;
  }

  /**
   * Create a mock NodeStorage which returns an array of Nodes
   *
   * @param UnitTestCase $unitTestCase
   * @param object[] $nodesToReturn
   * @return object
  */
  public static function createMockNodeStorage($unitTestCase, $method, $nodesToReturn) {
    $nodeStorage = $unitTestCase->getMockBuilder('Drupal\node\NodeStorage')
      ->disableOriginalConstructor()
      ->getMock();

    $nodeStorage->expects($unitTestCase->any())
      ->method($method)
      ->will($unitTestCase->returnValueMap($nodesToReturn));

    return $nodeStorage;
  }

  /**
   * Create a mock EntityManager
   *
   * @param UnitTestCase $unitTestCase
   * @param array $returnValueMap
   * @return object
  */
  public static function createMockEntityManager($unitTestCase, $returnValueMap) {
    $entityManager = $unitTestCase->getMockBuilder('Drupal\Core\Entity\EntityManagerInterface')
      ->disableOriginalConstructor()
      ->getMock();

    $entityManager->expects($unitTestCase->any())
      ->method('getStorage')
      ->will($unitTestCase->returnValueMap($returnValueMap));

    return $entityManager;
  }

  /**
   * Create a mock Field Item
   *
   * @param string $key
   * @param mixed $value
   * @return object
  */
  public function createFieldWith($key, $value) {
    $field = $this->unitTestCase->getMockBuilder('\Drupal\Core\Field\FieldItemListInterface')
      ->disableOriginalConstructor()
      ->getMock();

    $field->expects($this->unitTestCase->any())
      ->method('__get')
      ->with($key)
      ->willReturn($value);

    return $field;
  }

  /**
   * Create an empty Field Item
   *
   * @return object
  */
  public function createEmptyField() {
    $field = $this->unitTestCase->getMockBuilder('\Drupal\Core\Field\FieldItemListInterface')
      ->disableOriginalConstructor()
      ->getMock();

    $field->expects($this->unitTestCase->any())
      ->method('isEmpty')
      ->willReturn(true);

    return $field;
  }

  /**
   * Create a mock Description Field Item
   *
   * @param string $description
   * @return object
  */
  public function createDescriptionField($description) {
    $field = $this->unitTestCase->getMockBuilder('\Drupal\Core\Field\FieldItemListInterface')
      ->disableOriginalConstructor()
      ->getMock();

    $field->expects($this->unitTestCase->any())
      ->method('__get')
      ->with($this->unitTestCase->logicalOr(
        $this->unitTestCase->equalTo('raw'),
        $this->unitTestCase->equalTo('processed'),
        $this->unitTestCase->equalTo('summary'),
        $this->unitTestCase->equalTo('sanitized')
      ))
      ->willReturn($description);

    return $field;
  }
}
