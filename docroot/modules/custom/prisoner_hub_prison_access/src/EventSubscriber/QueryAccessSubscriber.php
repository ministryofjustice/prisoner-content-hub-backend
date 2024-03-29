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
   * QueryAccessSubscriber constructor.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The route match service.
   * @param string $prisonFieldName
   *   The prison field name.
   * @param string $excludeFromPrisonFieldName
   *   The name of the field specifying prisons to exclude.
   */
  public function __construct(
    protected EntityFieldManagerInterface $entityFieldManager,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected RouteMatchInterface $routeMatch,
    protected string $prisonFieldName,
    protected string $excludeFromPrisonFieldName,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events['entity.query_access'] = ['entityQueryAccessPrisonCategories'];

    return $events;
  }

  /**
   * Called when the entity.query_access.prison_categories is dispatched.
   *
   * @param \Drupal\entity\QueryAccess\QueryAccessEvent $event
   *   The dispatched event.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function entityQueryAccessPrisonCategories(QueryAccessEvent $event) {
    $operations = ['view', 'view label'];

    /** @var \Drupal\taxonomy\TermInterface $current_prison */
    $current_prison = $this->routeMatch->getParameter('prison');
    if (!in_array($event->getOperation(), $operations) || !$current_prison) {
      return;
    }
    $conditions = $event->getConditions();
    $conditions->alwaysFalse(FALSE);

    $prisons_condition_group = new ConditionGroup('OR');
    $prisons_condition_group->addCondition($this->prisonFieldName, $current_prison->id());

    // Exclude bundles (e.g. content types, vocabs, etc.). That do not have the
    // field enabled.  To do this we get a list of all bundles the field is
    // enabled, and add a 'NOT IN' condition.
    $entity_type = $this->entityTypeManager->getDefinition($event->getEntityTypeId());
    $bundles = $this->getFieldBundles($entity_type->id(), $this->prisonFieldName);
    $prisons_condition_group->addCondition($entity_type->getKey('bundle'), $bundles, 'NOT IN');

    // Load parents (aka prison categories) and filter by them as well.
    // Note that only initial parents will be loaded (i.e. not parents of
    // parents).
    // To load all parents, use:
    // $this->entityTypeManager->getStorage('taxonomy_term')->loadAllParents($current_prison->id());
    // Whilst this works, it finds parents via another entity query,
    // and so will run through this query alter.  This could have unexpected
    // consequences.  (So for now, we just deal with one level of parents).
    foreach ($current_prison->get('parent') as $parent) {
      $prisons_condition_group->addCondition($this->prisonFieldName, $parent->target_id);
    }

    $exclude_from_prison_condition_group = new ConditionGroup('OR');
    $exclude_from_prison_condition_group->addCondition($this->excludeFromPrisonFieldName, $current_prison->id(), '<>');
    $exclude_from_prison_condition_group->addCondition($this->excludeFromPrisonFieldName, NULL, 'IS NULL');

    $condition_group = new ConditionGroup('AND');
    $condition_group->addCondition($prisons_condition_group);
    $condition_group->addCondition($exclude_from_prison_condition_group);

    // Add status (i.e. published/unpublished) to the query, this is no longer
    // being added by jsonapi, since we have returned JSONAPI_FILTER_AMONG_ALL
    // in prisoner_hub_prison_access_jsonapi_entity_filter_access().
    if ($entity_type->hasKey('status')) {
      $condition_group->addCondition('status', 1);
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
  protected function getFieldBundles(string $entity_type_id, string $field_name) {
    $map = $this->entityFieldManager->getFieldMap();
    if (isset($map[$entity_type_id][$field_name]['bundles'])) {
      return $map[$entity_type_id][$field_name]['bundles'];
    }
    return [];
  }

}
