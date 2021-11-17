<?php


namespace Drupal\prisoner_hub_prison_access;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Drupal service for loading the current prison category.
 */
class PrisonCategoryLoader {

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
   * Get the current prison Taxonomy term ID from the route.
   *
   * @return int|null
   *   Either the prison Taxonomy term ID if exists, or NULL if one was not
   *   found on the current route.
   */
  public function getPrisonIdFromCurrentRoute() {
    $name = $this->routeMatch->getRouteName();
    $currentPrison = $this->routeMatch->getParameter('prison');
    return $currentPrison ? (int)$currentPrison->id() : NULL;
  }

  public function getPrisonCategoryIdFromCurrentRoute() {
    $currentPrison = $this->routeMatch->getParameter('prison');
    return $currentPrison ?  $this->getPrisonCategoryFromPrisonTerm($currentPrison) : $currentPrison;
  }

  public function getPrisonFieldName() {
    return $this->prisonFieldName;
  }

  public function getPrisonCategoryFieldName() {
    return $this->prisonCategoryFieldName;
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
  public function getPrisonCategoryFromPrisonTerm(TermInterface $term) {
    $field_value = $term->get($this->prisonCategoryFieldName)->getValue();
    return (int)$field_value[0]['target_id'];
  }

}
