<?php

namespace Drupal\prisoner_hub_prison_access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\taxonomy\Entity\Term;

/**
 * Drupal service to be used in conjunction with hook_entity_access().
 *
 * Note that hook_entity_access() only works on fully loaded entities, and not
 * when querying for a list of entities (e.g. via an entity query).
 */
class EntityAccessCheck {

  /**
   * The route match service.
   *
   * @var RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The prison field name.
   *
   * @var String
   */
  protected $prisonFieldName;

  /**
   * EntityAccessCheck constructor.
   */
  public function __construct(RouteMatchInterface $route_match, EntityTypeManager $entity_type_manager, string $prison_field_name) {
    $this->routeMatch = $route_match;
    $this->entityTypeManager = $entity_type_manager;
    $this->prisonFieldName = $prison_field_name;
  }

  /**
   * Check access of an entity, based on prison and prison category fields.
   *
   * This method should be called from hook_entity_access().
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity object, note that only content entities will be handled.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The AccessResult, determined by the entities prison and prison category
   *   fields
   */
  public function checkAccess(EntityInterface $entity) {
    /** @var \Drupal\taxonomy\TermInterface $current_prison */
    $current_prison = $this->routeMatch->getParameter('prison');
    // Only handle requests where there is a prison in the url.
    if (!$current_prison) {
      return AccessResult::neutral();
    }

    if ($entity instanceof ContentEntityInterface) {
      if ($entity->hasField($this->prisonFieldName)) {
        if ($this->fieldValueExists($entity->get($this->prisonFieldName), $current_prison->id())) {
          return AccessResult::neutral();
        }
        foreach ($current_prison->get('parent') as $parent) {
          if ($this->fieldValueExists($entity->get($this->prisonFieldName), (int)$parent->target_id)) {
            return AccessResult::neutral();
          }
        }
        return AccessResult::forbidden();
      }
    }
    return AccessResult::neutral();
  }

  /**
   * Check whether multivalue field contains a specific value.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field to check for the existence of a value.
   * @param $value
   *   The value to check.
   *
   * @return bool
   *   TRUE if $field contains $value, otherwise FALSE.
   */
  protected function fieldValueExists(FieldItemListInterface $field, $value) {
    $field_copy = clone $field;
    $field_copy->filter(function (TypedDataInterface $item) use ($value) {
      return $item->target_id == $value;
    });
    return $field_copy->count() > 0;
  }
}
