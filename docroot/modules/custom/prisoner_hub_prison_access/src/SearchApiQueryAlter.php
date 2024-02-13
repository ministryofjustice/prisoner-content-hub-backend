<?php

namespace Drupal\prisoner_hub_prison_access;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\search_api\Query\ConditionGroup;
use Drupal\search_api\Query\QueryInterface;

/**
 * Service to alter Search API queries to implement prison category filtering.
 */
class SearchApiQueryAlter {

  /**
   * SearchApiQueryAlter constructor.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The route match service.
   * @param string $prisonFieldName
   *   The prison field name.
   * @param string $excludeFromPrisonFieldName
   *   The name of the field specifying prisons to exclude.
   */
  public function __construct(
    protected RouteMatchInterface $routeMatch,
    protected string $prisonFieldName,
    protected string $excludeFromPrisonFieldName,
  ) {
  }

  /**
   * To be used in conjunction with hook_search_api_query_alter().
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The query to alter.
   */
  public function searchApiQueryAlter(QueryInterface $query) {
    /** @var \Drupal\taxonomy\TermInterface $current_prison */
    $current_prison = $this->routeMatch->getParameter('prison');
    if (!$current_prison) {
      return;
    }

    $prisons_condition_group = new ConditionGroup('OR');
    $prisons_condition_group->addCondition($this->prisonFieldName, $current_prison->id());

    foreach ($current_prison->get('parent') as $parent) {
      $prisons_condition_group->addCondition($this->prisonFieldName, $parent->target_id);
    }
    $query->addConditionGroup($prisons_condition_group);

    $exclude_from_prison_condition_group = new ConditionGroup('OR');
    $exclude_from_prison_condition_group->addCondition($this->excludeFromPrisonFieldName, $current_prison->id(), '<>');
    $exclude_from_prison_condition_group->addCondition($this->excludeFromPrisonFieldName, NULL);
    $query->addConditionGroup($exclude_from_prison_condition_group);
  }

}
