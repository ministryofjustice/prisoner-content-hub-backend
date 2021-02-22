<?php

namespace Drupal\Tests\prisoner_hub_entity_access\Unit;

use Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher as EventDispatcher;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Session\UserSession;
use Drupal\entity\QueryAccess\Condition;
use Drupal\entity\QueryAccess\ConditionGroup;
use Drupal\entity\QueryAccess\QueryAccessEvent;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBaseTest;
use Drupal\prisoner_hub_entity_access\EventSubscriber\QueryAccessSubscriber;
use Drupal\prisoner_hub_prison_context\PrisonContext;
use Drupal\Tests\token\Kernel\KernelTestBase;
use Drupal\Tests\UnitTestCase;

/**
 * Tests query access filtering for carts.
 *
 * @coversDefaultClass \Drupal\prisoner_hub_entity_access\EventSubscriber\QueryAccessSubscriber
 * @group prisoner_hub_entity_access
 */
class QueryAccessTest extends UnitTestCase {

  public function setUp() {
    parent::setUp();

  }

  public function testQueryAccessSubscriber() {
    $prisonContextMock = $this->getMockBuilder(PrisonContext::class)
      ->disableOriginalConstructor()
      ->getMock();
    $prisonContextMock->expects($this->once())
      ->method('prisonContextExists')
      ->willReturn(TRUE);
    $prisonContextMock->expects($this->once())
      ->method('getPrisonCategories')
      ->willReturn([123]);
    $prisonContextMock->expects($this->once())
      ->method('getPrisonCategoryFieldName')
      ->willReturn('field_prison_category_test');

    $this->prisonContext = $prisonContextMock;

    $entityFieldManagerMock = $this->getMockBuilder(EntityFieldManager::class)
      ->disableOriginalConstructor()
      ->getMock();
    $entityFieldManagerMock->expects($this->once())
      ->method('getFieldMap')
      ->willReturn('test_bundle_without_field_A', 'test_bundle_without_field_B');
      //->willReturn(['field_test_1', 'field_test_2']);
    $this->entityFieldManager = $entityFieldManagerMock;

    $dispatcher = new EventDispatcher(\Drupal::getContainer());
    $subscriber = new QueryAccessSubscriber($this->entityFieldManager, $this->prisonContext);
    $dispatcher->addSubscriber($subscriber);
    $event = new QueryAccessEvent(new ConditionGroup(), 'view', new UserSession(), 'node');
    $dispatcher->dispatch('entity.query_access.node', $event);

    $this->assertFalse($event->getConditions()->isAlwaysFalse());
//$event->getConditions()->
    //$conditionGroup = new ConditionGroup('OR');
    //$conditionGroup->addCondition()
    //$conditionGroup->addCondition()
    $conditions = $event->getConditions();
    $this->assertEquals(1, $conditions->count());
    /* @var \Drupal\entity\QueryAccess\ConditionGroup $conditionsGroup */
    $conditionsGroup = $conditions->getConditions();
    $this->assertTrue($conditionsGroup instanceof ConditionGroup);
    $this->assertTrue($conditionsGroup->getConjunction() === 'OR');
    $this->assertTrue($conditionsGroup->getConditions()[0]->getField() === 'field_prison_category_test');
    $this->assertTrue($conditionsGroup->getConditions()[0]->getValue() === 123);



  }
}
