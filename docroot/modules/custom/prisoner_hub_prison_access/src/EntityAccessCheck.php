<?php

namespace Drupal\prisoner_hub_prison_access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TypedData\TypedDataInterface;

/**
 * Drupal service to be used in conjunction with hook_entity_access().
 *
 * Note that hook_entity_access() only works on fully loaded entities, and not
 * when querying for a list of entities (e.g. via an entity query).
 */
class EntityAccessCheck {

  /**
   * EntityAccessCheck constructor.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The route match service.
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The entity type manager.
   * @param string $prisonFieldName
   *   The name of the prison field.
   * @param string $excludeFromPrisonFieldName
   *   The name of the prison exclude field.
   */
  public function __construct(
    protected RouteMatchInterface $routeMatch,
    protected EntityTypeManager $entityTypeManager,
    protected string $prisonFieldName,
    protected string $excludeFromPrisonFieldName,
  ) {
  }

  /**
   * Check access of an entity, based on prison and prison category fields.
   *
   * This method should be called from hook_entity_access().
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity object, note that only content entities will be handled.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Account for which we are checking access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The AccessResult, determined by the entities prison and prison category
   *   fields
   */
  public function checkAccess(EntityInterface $entity, AccountInterface $account) {
    $entity_types = ['node', 'taxonomy_term'];
    if (!in_array($entity->getEntityTypeId(), $entity_types)) {
      return AccessResult::neutral();
    }
    /** @var \Drupal\taxonomy\TermInterface $current_prison */
    $current_prison = $this->routeMatch->getParameter('prison');
    if (!$current_prison) {
      // If no prison context in the url, check if the user has permission
      // to view without one. If so, return neutral, otherwise return forbidden.
      return AccessResult::forbiddenIf($account->hasPermission('view entity without prison context') == FALSE);
    }

    if ($entity instanceof ContentEntityInterface) {
      if ($entity->hasField($this->excludeFromPrisonFieldName) && $this->fieldValueExists($entity->get($this->excludeFromPrisonFieldName), $current_prison->id())) {
        return AccessResult::forbidden();
      }
      if ($entity->hasField($this->prisonFieldName)) {
        if ($this->fieldValueExists($entity->get($this->prisonFieldName), $current_prison->id())) {
          return AccessResult::neutral();
        }
        foreach ($current_prison->get('parent') as $parent) {
          if ($this->fieldValueExists($entity->get($this->prisonFieldName), (int) $parent->target_id)) {
            return AccessResult::neutral();
          }
        }
        return AccessResult::forbidden();
      }
    }
    return AccessResult::neutral();
  }

  /**
   * Check whether multi-value field contains a specific value.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field to check for the existence of a value.
   * @param string|int|null $value
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
