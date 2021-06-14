<?php


namespace Drupal\prisoner_hub_entity_access;

use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Abstract base class for implementing prison category filtering.
 *
 * This class provides a link between the different query alter API's that we
 * need to implement (i.e. QueryAccess API and Search API).
 */
abstract class QueryAlterBase {

  /**
   * The route match service.
   *
   * @var RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The prison category field name.
   *
   * @var String
   */
  protected $prisonCategoryFieldName;

  /**
   * The prison field name.
   *
   * @var String
   */
  protected $prisonFieldName;

  public function __construct(RouteMatchInterface $route_match, string $prison_field_name, string $prison_category_field_name) {
    $this->routeMatch = $route_match;
    $this->prisonFieldName = $prison_field_name;
    $this->prisonCategoryFieldName = $prison_category_field_name;
  }

  /**
   * Get the current prison Taxonomy term from the route.
   *
   * @return \Drupal\taxonomy\TermInterface|null
   *   Either the prison Taxonomy term if exists, or NULL if one was not found
   *   on the current route.
   */
  protected function getCurrentPrison() {
    return $this->routeMatch->getParameter('prison');
  }

  /**
   * Get a flattened array of prison category term ids.
   *
   * @param \Drupal\taxonomy\TermInterface $term
   *   The prison taxonomy term to check for the category.
   *
   * @return int
   *   The term ID of the prison category.  Note only the first category is
   *   returned (in the case of two or more prison categories assigned to the
   *   current prison).
   */
  protected function getPrisonCategory(TermInterface $term) {
    $field_value = $term->get($this->prisonCategoryFieldName)->getValue();
    return (int)$field_value[0]['target_id'];
  }

}
