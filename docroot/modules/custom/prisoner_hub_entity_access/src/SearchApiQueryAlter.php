<?php

namespace Drupal\prisoner_hub_entity_access;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\search_api\Query\ConditionGroup;
use Drupal\search_api\Query\QueryInterface;

/**
 * Drupal service that alters Search API queries to implement prison category
 * filtering.
 */
class SearchApiQueryAlter {

  /**
   * The prison category loader service.
   *
   * @var \Drupal\prisoner_hub_entity_access\PrisonCategoryLoader
   */
  protected $prisonCategoryLoader;

  /**
   * SearchApiQueryAlter constructor.
   *
   * @param \Drupal\prisoner_hub_entity_access\PrisonCategoryLoader $prison_category_loader
   */
  public function __construct(PrisonCategoryLoader $prison_category_loader) {
    $this->prisonCategoryLoader = $prison_category_loader;
  }

  /**
   * This method is to be used in conjunction with hook_search_api_query_alter().
   *
   * @param \Symfony\Component\EventDispatcher\Event $event
   *   The dispatched event.
   */
  public function searchApiQueryAlter(QueryInterface $query) {

    /* @var \Drupal\taxonomy\TermInterface $current_prison */
    $current_prison_id = $this->prisonCategoryLoader->getPrisonIdFromCurrentRoute();
    if (!$current_prison_id) {
      return;
    }

    $condition_group = new ConditionGroup('OR');
    $condition_group->addCondition($this->prisonCategoryLoader->getPrisonFieldName(), $current_prison_id);

    $prison_category_id = $this->prisonCategoryLoader->getPrisonCategoryIdFromCurrentRoute();
    $condition_group->addCondition($this->prisonCategoryLoader->getPrisonCategoryFieldName(), $prison_category_id);

    $query->addConditionGroup($condition_group);

  }

}
