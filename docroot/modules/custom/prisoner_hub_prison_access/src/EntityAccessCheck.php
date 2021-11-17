<?php

namespace Drupal\prisoner_hub_prison_access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\TypedData\TypedDataInterface;

/**
 * Drupal service to be used in conjunction with hook_entity_access().
 *
 * Note that hook_entity_access() only works on fully loaded entities, and not
 * when querying for a list of entities (e.g. via an entity query).
 */
class EntityAccessCheck {

  /**
   * The prison category loader service.
   *
   * @var \Drupal\prisoner_hub_prison_access\PrisonCategoryLoader
   */
  protected $prisonCategoryLoader;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * EntityAccessCheck constructor.
   *
   * @param \Drupal\prisoner_hub_prison_access\PrisonCategoryLoader $prison_category_loader
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   */
  public function __construct(PrisonCategoryLoader $prison_category_loader, EntityTypeManager $entity_type_manager) {
    $this->prisonCategoryLoader = $prison_category_loader;
    $this->entityTypeManager = $entity_type_manager;
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
    $current_prison_id = $this->prisonCategoryLoader->getPrisonIdFromCurrentRoute();

    // Only handle requests where there is a prison in the url.
    if (!$current_prison_id) {
      return AccessResult::neutral();
    }

    if ($entity instanceof ContentEntityInterface) {
      $prison_field_name = $this->prisonCategoryLoader->getPrisonFieldName();
      $prison_category_field_name = $this->prisonCategoryLoader->getPrisonCategoryFieldName();
      if ($entity->hasField($prison_field_name) && $entity->hasField($prison_category_field_name)) {
        if ($this->fieldValueExists($entity->get($prison_field_name), $current_prison_id)) {
          return AccessResult::neutral();
        }
        if ($this->fieldValueExists($entity->get($prison_category_field_name), $this->prisonCategoryLoader->getPrisonCategoryIdFromCurrentRoute())) {
          return AccessResult::neutral();
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
    $field->filter(function (TypedDataInterface $item) use ($value) {
      return $item->target_id == $value;
    });
    return $field->count() > 0;
  }
}
