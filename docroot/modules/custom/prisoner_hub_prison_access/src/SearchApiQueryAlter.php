<?php

namespace Drupal\prisoner_hub_prison_access;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\search_api\Query\ConditionGroup;
use Drupal\search_api\Query\QueryInterface;

/**
 * Drupal service that alters Search API queries to implement prison category
 * filtering.
 */
class SearchApiQueryAlter {

  /**
   * The route match service.
   *
   * @var RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The prison field name.
   *
   * @var String
   */
  protected $prisonFieldName;

  /**
   * SearchApiQueryAlter constructor.
   */
  public function __construct(RouteMatchInterface $route_match, string $prison_field_name) {
    $this->routeMatch = $route_match;
    $this->prisonFieldName = $prison_field_name;
  }

  /**
   * This method is to be used in conjunction with hook_search_api_query_alter().
   *
   * @param \Symfony\Component\EventDispatcher\Event $event
   *   The dispatched event.
   */
  public function searchApiQueryAlter(QueryInterface $query) {
    /* @var \Drupal\taxonomy\TermInterface $current_prison */
    $current_prison = $this->routeMatch->getParameter('prison');
    if (!$current_prison) {
      return;
    }

    $condition_group = new ConditionGroup('OR');
    $condition_group->addCondition($this->prisonFieldName, $current_prison->id());

    foreach ($current_prison->get('parent') as $parent) {
      $condition_group->addCondition($this->prisonFieldName, $parent->target_id);
    }

    $query->addConditionGroup($condition_group);
  }
}
