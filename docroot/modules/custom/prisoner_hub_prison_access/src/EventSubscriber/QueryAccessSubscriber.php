<?php

namespace Drupal\prisoner_hub_prison_access\EventSubscriber;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\entity\QueryAccess\ConditionGroup;
use Drupal\entity\QueryAccess\QueryAccessEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class QueryAccessSubscriber.
 *
 * Uses the entity query access API (from the entity module).  To show/hide
 * content based on the prison field.
 */
class QueryAccessSubscriber implements EventSubscriberInterface {

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

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
   * QueryAccessSubscriber constructor.
   */
  public function __construct(EntityFieldManagerInterface $entity_field_manager, EntityTypeManagerInterface $entity_type_manager, RouteMatchInterface $route_match, string $prison_field_name) {
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->routeMatch = $route_match;
    $this->prisonFieldName = $prison_field_name;
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
    $current_prison = $this->routeMatch->getParameter('prison');
    if (!in_array($event->getOperation(), $operations) || !$current_prison) {
      return;
    }
    $conditions = $event->getConditions();
    $conditions->alwaysFalse(FALSE);

    $condition_group = new ConditionGroup('OR');
    $condition_group->addCondition($this->prisonFieldName, $current_prison->id());

    // Exclude bundles (e.g. content types, vocabs, etc).  That do not have the
    // field enabled.  To do this we get a list of all bundles the field is
    // enabled, and add a 'NOT IN' condition.
    $entity_type = $this->entityTypeManager->getDefinition($event->getEntityTypeId());
    $bundles = $this->getFieldBundles($entity_type->id(), $this->prisonFieldName);
    $condition_group->addCondition($entity_type->getKey('bundle'), $bundles, 'NOT IN');

    // Load parents (aka prison categories) and filter by them as well.
    // Note that only initial parents will be loaded (i.e. not parents of parents).
    // To load all parents, use:
    // $this->entityTypeManager->getStorage('taxonomy_term')->loadAllParents($current_prison->id());
    // Note that whilst this works, it finds parents via another another entity query,
    // and so will run through this query alter.  This could have unexpected
    // consequences.  (So for now, we just deal with one level of parents).
    foreach ($current_prison->get('parent') as $parent) {
      $condition_group->addCondition($this->prisonFieldName, $parent->target_id);
    }

    $conditions->addCondition($condition_group);

  }

  /**
   * Get the bundles (e.g. content types, vocabs) that have a certain field.
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
