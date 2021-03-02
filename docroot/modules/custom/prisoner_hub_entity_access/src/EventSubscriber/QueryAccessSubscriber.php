<?php

namespace Drupal\prisoner_hub_entity_access\EventSubscriber;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\entity\QueryAccess\ConditionGroup;
use Drupal\entity\QueryAccess\QueryAccessEvent;
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
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
  */
  protected $entityFieldManager;

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
   * The prison category field name.
   *
   * @var String
   */
  protected $prisonFieldName;

  public function __construct(EntityFieldManagerInterface $entity_field_manager, RouteMatchInterface $route_match, string $prison_field_name, string $prison_category_field_name) {
    $this->entityFieldManager = $entity_field_manager;
    $this->routeMatch = $route_match;
    $this->prisonFieldName = $prison_field_name;
    $this->prisonCategoryFieldName = $prison_category_field_name;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // TODO: Do we want to run this for Taxonomy Terms?  Or other entity types?
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

    /* @var \Drupal\taxonomy\TermInterface $current_prison */
    $current_prison = $this->routeMatch->getParameter('prison');
    if (!in_array($event->getOperation(), $operations) || !$current_prison) {
      return;
    }

    $conditions = $event->getConditions();
    $conditions->alwaysFalse(FALSE);

    $current_prison_condition_group = $this->getPrisonConditionGroup($current_prison);
    $prison_categories_condition_group = $this->getPrisonCategoriesConditionGroup($current_prison);

    // Only create an OR condition group if a prison categories condition is to
    // be added.
    if ($prison_categories_condition_group) {
      $condition_group = new ConditionGroup('OR');
      $condition_group->addCondition($current_prison_condition_group);
      $condition_group->addCondition($prison_categories_condition_group);
      $conditions->addCondition($condition_group);
    }
    else {
      $conditions->addCondition($current_prison_condition_group);
    }
  }

  /**
   * Get the condition group for content tagged with the current prison.
   *
   * @param \Drupal\taxonomy\TermInterface $current_prison
   *   The current prison taxonomy term (loaded via the url).
   *
   * @return \Drupal\entity\QueryAccess\ConditionGroup
   *   The condition group, to be added to the entity query.
   */
  protected function getPrisonConditionGroup(TermInterface $current_prison) {
    $condition_group = new ConditionGroup('OR');
    $condition_group->addCondition($this->prisonFieldName, $current_prison->id());
    $bundles = $this->getFieldBundles('node', $this->prisonFieldName);
    $condition_group->addCondition('type', $bundles, 'NOT IN');
    return $condition_group;
  }

  /**
   * Get the condition for content tagged with the current prison category(ies).
   *
   * @param \Drupal\taxonomy\TermInterface $current_prison
   *   The current prison taxonomy term (loaded via the url).
   *
   * @return \Drupal\entity\QueryAccess\ConditionGroup|NULL
   *   The condition group, to be added to the entity query.  Or NULL if no
   *   condition should be added.
   */
  protected function getPrisonCategoriesConditionGroup(TermInterface $current_prison) {
    $prison_categories = $this->getPrisonCategories($current_prison);
    if (empty($prison_categories)) {
      return NULL;
    }

    $condition_group_parent = new ConditionGroup('AND');
    // First check that the prison field is empty.  As if this field has been
    // set it should override the prison categories field.
    $check_prison_field_is_empty_condition_group = new ConditionGroup('OR');
    $check_prison_field_is_empty_condition_group->addCondition($this->prisonFieldName, 'IS NULL');
    $bundles = $this->getFieldBundles('node', $this->prisonFieldName);
    $check_prison_field_is_empty_condition_group->addCondition('type', $bundles, 'NOT IN');
    $condition_group_parent->addCondition($check_prison_field_is_empty_condition_group);

    $bundles = $this->getFieldBundles('node', $this->prisonCategoryFieldName);
    $check_prison_categories_condition_group = new ConditionGroup('OR');
    $check_prison_categories_condition_group->addCondition($this->prisonCategoryFieldName, $prison_categories);
    $check_prison_categories_condition_group->addCondition('type', $bundles, 'NOT IN');
    $condition_group_parent->addCondition($check_prison_categories_condition_group);
    return $condition_group_parent;
  }

  /**
   * Get a flattened array of prison category term ids.
   *
   * @param \Drupal\taxonomy\TermInterface $term
   *   The taxonomy term to check for categories.
   *
   * @return array
   *   A flat array with category term ids.  This can be empty if the $term has
   *   no categories associated with it.
   */
  protected function getPrisonCategories(TermInterface $term) {
    $field_value = $term->get($this->prisonCategoryFieldName)->getValue();
    $categories = [];
    if (!empty($field_value)) {
      foreach ($field_value as $value) {
        $categories[] = $value['target_id'];
      }
    }
    return $categories;
  }

  /**
   * Get the bundles (content types) that have a certain field.
   *
   * @param string $entity_type_id
   *   The entity type id, e.g. "node".
   * @param string $field_name
   *   The field name, e.g. "field_prison_categories".
   *
   * @return array
   *   A flat array, containing each bundle name as a string.
   */
  protected function getFieldBundles($entity_type_id, $field_name) {
    $map = $this->entityFieldManager->getFieldMap();
    if (isset($map[$entity_type_id][$field_name]['bundles'])) {
      return $map[$entity_type_id][$field_name]['bundles'];
    }
    return [];
  }
}
