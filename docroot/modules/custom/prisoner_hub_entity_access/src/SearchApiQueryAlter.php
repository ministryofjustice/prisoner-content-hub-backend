<?php

namespace Drupal\prisoner_hub_entity_access;

use Drupal\search_api\Query\ConditionGroup;
use Drupal\search_api\Query\QueryInterface;

/**
 * Drupal service that alters Search API queries to implement prison category
 * filtering.
 */
class SearchApiQueryAlter extends QueryAlterBase {

  /**
   * This method is called when the entity.query_access.prison_categories is dispatched.
   *
   * @param \Symfony\Component\EventDispatcher\Event $event
   *   The dispatched event.
   */
  public function searchApiQueryAlter(QueryInterface $query) {

    /* @var \Drupal\taxonomy\TermInterface $current_prison */
    $current_prison = $this->getCurrentPrison();
    if (!$current_prison) {
      return;
    }

    $condition_group = new ConditionGroup('OR');
    $condition_group->addCondition($this->prisonFieldName, (int)$current_prison->id());

    $prison_category = $this->getPrisonCategory($current_prison);
    $condition_group->addCondition($this->prisonCategoryFieldName, $prison_category);

    $query->addConditionGroup($condition_group);

  }

}
