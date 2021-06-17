<?php

namespace Drupal\prisoner_hub_entity_access\EventSubscriber;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\entity\QueryAccess\ConditionGroup;
use Drupal\entity\QueryAccess\QueryAccessEvent;
use Drupal\prisoner_hub_entity_access\QueryAlterBase;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class QueryAccessSubscriber.
 *
 * Uses the entity query access API (from the entity module).  To show/hide
 * content based on prison and prison category fields.
 */
class QueryAccessSubscriber extends QueryAlterBase implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events['entity.query_access'] = ['entityQueryAccessPrisonCategories'];

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

    /* @var \Drupal\taxonomy\TermInterface $current_prison */
    $current_prison = $this->getCurrentPrison();
    if (!in_array($event->getOperation(), $operations) || !$current_prison) {
      return;
    }

    $conditions = $event->getConditions();
    $conditions->alwaysFalse(FALSE);

    $condition_group = new ConditionGroup('OR');
    $condition_group->addCondition($this->prisonFieldName, $current_prison->id());

    $prison_category = $this->getPrisonCategory($current_prison);
    $condition_group->addCondition($this->prisonCategoryFieldName, $prison_category);
    $conditions->addCondition($condition_group);

  }

}
