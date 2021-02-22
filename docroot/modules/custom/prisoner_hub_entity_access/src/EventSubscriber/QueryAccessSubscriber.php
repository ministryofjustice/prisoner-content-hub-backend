<?php

namespace Drupal\prisoner_hub_entity_access\EventSubscriber;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\entity\QueryAccess\ConditionGroup;
use Drupal\entity\QueryAccess\QueryAccessEvent;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\prisoner_hub_prison_context\PrisonContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class QueryAccessSubscriber.
 */
class QueryAccessSubscriber implements EventSubscriberInterface {

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
  */
  protected $entityFieldManager;

  /**
   * The request stack.
   *
   * @var PrisonContext;
   */
  protected $prisonContext;

  public function __construct(EntityFieldManagerInterface $entity_field_manager, PrisonContext $prison_context) {
    $this->entityFieldManager = $entity_field_manager;
    $this->prisonContext = $prison_context;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events['entity.query_access.node'] = ['entityQueryAccessPrisonCategories'];

    return $events;
  }

  /**
   * This method is called when the entity.query_access.prison_categories is dispatched.
   *
   * @param \Symfony\Component\EventDispatcher\Event $event
   *   The dispatched event.
   */
  public function entityQueryAccessPrisonCategories(QueryAccessEvent $event) {
    $operations = ['view', 'view label'];
    if (in_array($event->getOperation(), $operations) || !$this->prisonContext->prisonContextExists()) {
      return;
    }

    $prison_categories = $this->prisonContext->getPrisonCategories();
    $bundles = $this->getFieldBundles('node', $this->prisonContext->getPrisonCategoryFieldName());
    $conditions = $event->getConditions();
    $conditions->alwaysFalse(FALSE);
    $condition_group = new ConditionGroup('OR');
    $condition_group->addCondition($this->prisonContext->getPrisonCategoryFieldName(), $prison_categories);
    $condition_group->addCondition('type', $bundles, 'NOT IN');
    $conditions->addCondition($condition_group);

  }


  private function getFieldBundles($entity_type_id, $field_name) {
    $map = $this->entityFieldManager->getFieldMap();
    if (isset($map[$entity_type_id][$field_name]['bundles'])) {
      return $map[$entity_type_id][$field_name]['bundles'];
    }
    return [];
  }

}
