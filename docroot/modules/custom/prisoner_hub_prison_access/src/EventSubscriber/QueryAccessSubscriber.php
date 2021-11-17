<?php

namespace Drupal\prisoner_hub_prison_access\EventSubscriber;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\entity\QueryAccess\ConditionGroup;
use Drupal\entity\QueryAccess\QueryAccessEvent;
use Drupal\prisoner_hub_prison_access\PrisonCategoryLoader;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class QueryAccessSubscriber.
 *
 * Uses the entity query access API (from the entity module).  To show/hide
 * content based on prison and prison category fields.
 */
class QueryAccessSubscriber implements EventSubscriberInterface {

  /**
   * The prison category loader service.
   *
   * @var \Drupal\prisoner_hub_prison_access\PrisonCategoryLoader
   */
  protected $prisonCategoryLoader;

  /**
   * SearchApiQueryAlter constructor.
   *
   * @param \Drupal\prisoner_hub_prison_access\PrisonCategoryLoader $prison_category_loader
   */
  public function __construct(PrisonCategoryLoader $prison_category_loader) {
    $this->prisonCategoryLoader = $prison_category_loader;
  }

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
    $current_prison_id = $this->prisonCategoryLoader->getPrisonIdFromCurrentRoute();
    if (!in_array($event->getOperation(), $operations) || !$current_prison_id) {
      return;
    }

    $conditions = $event->getConditions();
    $conditions->alwaysFalse(FALSE);

    $condition_group = new ConditionGroup('OR');
    $condition_group->addCondition($this->prisonCategoryLoader->getPrisonFieldName(), $current_prison_id);

    $current_prison_category_id = $this->prisonCategoryLoader->getPrisonCategoryIdFromCurrentRoute();
    $condition_group->addCondition($this->prisonCategoryLoader->getPrisonCategoryFieldName(), $current_prison_category_id);
    $conditions->addCondition($condition_group);

  }

}
