<?php

namespace Drupal\Tests\moj_resources\Unit\Supports;

use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\moj_resources\SeriesContentApiClass;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Test Helpers for Unit tests
 *
 * @group unit_moj_resources
 */

class TestHelpers {
  /**
   * Create a mock node
   *
   * @param \Drupal\Tests\UnitTestCase $unitTestCase
   * @return object
  */
  public static function createMockNode($unitTestCase, $content) {
    $node = $unitTestCase->getMockBuilder('Drupal\node\Entity\Node')
      ->disableOriginalConstructor()
      ->getMock();

    $node->expects($unitTestCase->any())
      ->method('access')
      ->willReturn(true);

    $node->expects($unitTestCase->any())
      ->method("__get")
      ->will($unitTestCase->returnValueMap($content->createReturnValueMap()));

    return $node;
  }
  /**
   * Create a mock QueryFactory which returns an array of Node IDs
   *
   * @param \Drupal\Tests\UnitTestCase $unitTestCase
   * @param int[] $nodeIdsToReturn
   * @return object
  */
  public static function createMockQueryFactory($unitTestCase, $nodeIdsToReturn) {
    $queryFactory = $unitTestCase->getMockBuilder('Drupal\Core\Entity\Query\QueryFactory')
      ->disableOriginalConstructor()
      ->setMethods(array('get', 'condition', 'sort', 'range', 'execute', 'accessCheck'))
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
   * @param \Drupal\Tests\UnitTestCase $unitTestCase
   * @param object[] $nodesToReturn
   * @return object
  */
  public static function createMockNodeStorage($unitTestCase, $nodesToReturn) {
    $nodeStorage = $unitTestCase->getMockBuilder('Drupal\node\NodeStorage')
      ->disableOriginalConstructor()
      ->getMock();

    $nodeStorage->expects($unitTestCase->any())
      ->method('loadMultiple')
      ->will($unitTestCase->returnValue($nodesToReturn));

    return $nodeStorage;
  }
  /**
   * Create a mock EntityManager
   *
   * @param \Drupal\Tests\UnitTestCase $unitTestCase
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
}
