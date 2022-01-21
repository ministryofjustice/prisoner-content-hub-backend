<?php

namespace Drupal\prisoner_hub_prison_access_cms;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\user\Entity\User;

/**
 * The EntityEditAccess service.
 *
 * This provides access checks to see whether the user has access to edit an
 * entity.
 */
class EntityEditAccess {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $user;

  /**
   * EntityEditAccess constructor.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   * @param string $prison_owner_field_name
   *   The field name of the prison owner field, added to ContentEntities.
   * @param string $user_prison_field_name
   *   The field name where the users prisons are stored, on the user entity.s
   * @param string $exclude_from_prison_field_name
   *   The field name of the excluded prisons, added to ContentEntities.
   */
  public function __construct(AccountInterface $account, string $prison_owner_field_name, string $user_prison_field_name, string $exclude_from_prison_field_name) {
    $this->user = User::load($account->id());
    $this->prisonOwnerFieldName = $prison_owner_field_name;
    $this->userPrisonFieldName = $user_prison_field_name;
    $this->excludeFromPrisonFieldName = $exclude_from_prison_field_name;
    $this->userPrisons = $this->user->hasField($this->userPrisonFieldName) ? $this->user->get($this->userPrisonFieldName)->referencedEntities() : [];
  }

  /**
   * Checks whether the current user has access to a specific field
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition of the field to check.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to check the user has access to.
   *
   * @return bool
   *   TRUE if the user can edit the field, otherwise FALSE.
   */
  public function hasFieldAccess(FieldDefinitionInterface $fieldDefinition, ContentEntityInterface $entity) {
    // If the entity is being created, then assume we have access to all of
    // the fields.  I.e. we only restrict access for existing entities (that are
    // potentially created by other users/prisons).
    if ($entity->isNew()) {
      return TRUE;
    }

    // The exclude from prison field is always accessible.
    if ($fieldDefinition->getName() == $this->excludeFromPrisonFieldName) {
      return TRUE;
    }

    if ($fieldDefinition->getName() == 'revision_log') {
      return TRUE;
    }

    return $this->hasEntityAccess($entity);
  }

  /**
   * Check whether the user has edit access to the $entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to check.
   *
   * @return bool
   *   TRUE if the user has edit access, otherwise FALSE.
   */
  public function hasEntityAccess(ContentEntityInterface $entity) {
    if ($this->user->hasPermission('bypass prison ownership edit access')) {
      return TRUE;
    }

    // Allow content authors to edit their own account, regardless of prison.
    if ($entity instanceof NodeInterface && $entity->getOwnerId() == $this->user->id()) {
      return TRUE;
    }

    $content_prisons = $entity->hasField($this->prisonOwnerFieldName) ? $entity->get($this->prisonOwnerFieldName)->referencedEntities() : [];
    // If the content is not owned by any prison, then deny access.
    if (empty($content_prisons)) {
      return FALSE;
    }

    /** @var \Drupal\taxonomy\TermInterface $user_prison */
    foreach ($this->userPrisons as $user_prison) {
      /** @var \Drupal\taxonomy\TermInterface $content_prison */
      foreach ($content_prisons as $content_prison) {
        if ($user_prison->id() == $content_prison->id()) {
          return TRUE;
        }
        // Check if $user_prison is a parent category, and matches the parent
        // of $content_prison.
        // E.g. $user_prison = "Adult male", $content_prison = "Berwyn".
        foreach ($content_prison->get('parent') as $content_prison_parent) {
          if ($content_prison_parent->target_id == $user_prison->id()) {
            return TRUE;
          }
        }
        // Check if $content_prison is a parent category, and matches the parent
        // of $user_prison.
        // E.g. $content_prison = "Adult male", $user_prison = "Berwyn".
        foreach ($user_prison->get('parent') as $user_prison_parent) {
          if ($user_prison_parent->target_id == $content_prison->id()) {
            return TRUE;
          }
        }
      }
    }
    // If no prison field matches, deny access to the field.
    return FALSE;
  }

  /**
   * Check an individual prison $id to see if the user has access.
   *
   * This will also include prison categories, if a user has been assigned to a
   * category and the $id is for a prison within this category, the result will
   * be TRUE.
   *
   * @param int $id
   *   The numerical taxonomy term id of the prison to check for.
   *
   * @return bool
   *   TRUE if the user has access to the prison $id, otherwise FALSE.
   */
  public function hasPrisonTermAccess(int $id) {
    $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    /** @var TermInterface $user_prison_term */
    foreach ($this->userPrisons as $user_prison_term) {
      if ($user_prison_term->id() == $id) {
        return TRUE;
      }
      foreach ($storage->getChildren($user_prison_term) as $child_term) {
        if ($child_term->id() == $id) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  /**
   * Get the current user prisons.
   *
   * @return array
   */
  public function getUserPrisons() {
    return $this->userPrisons;
  }

}
