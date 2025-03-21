<?php

namespace Drupal\prisoner_hub_prison_access_cms;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

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
   * The prison entities assigned to the user.
   */
  protected array $userPrisons;

  /**
   * EntityEditAccess constructor.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   * @param string $prisonOwnerFieldName
   *   The field name of the prison owner field, added to ContentEntities.
   * @param string $userPrisonFieldName
   *   The field name where the users prisons are stored, on the user entity.
   * @param string $prisonFieldName
   *   The field name of the included prisons, added to ContentEntities.
   * @param string $excludeFromPrisonFieldName
   *   The field name of the excluded prisons, added to ContentEntities.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown if the entity type doesn't exist.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown if the storage handler couldn't be loaded.
   */
  public function __construct(
    AccountInterface $account,
    protected string $prisonOwnerFieldName,
    protected string $userPrisonFieldName,
    protected string $prisonFieldName,
    protected string $excludeFromPrisonFieldName,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    $this->user = $this->entityTypeManager->getStorage('user')->load($account->id());
    $this->userPrisons = $this->user->hasField($this->userPrisonFieldName) ? $this->user->get($this->userPrisonFieldName)->referencedEntities() : [];
  }

  /**
   * Checks whether the current user has access to a specific field.
   *
   * @param string $fieldName
   *   The field name of the field to check.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to check the user has access to.
   *
   * @return bool
   *   TRUE if the user can edit the field, otherwise FALSE.
   */
  public function hasFieldAccess(string $fieldName, ContentEntityInterface $entity) {
    // If the entity is being created, then assume we have access to all
    // the fields.  I.e. we only restrict access for existing entities (that are
    // potentially created by other users/prisons).
    if ($entity->isNew()) {
      return TRUE;
    }

    // The prison field is always accessible.
    if ($fieldName == $this->prisonFieldName) {
      return TRUE;
    }

    // The exclude from prison field is always accessible.
    if ($fieldName == $this->excludeFromPrisonFieldName) {
      return TRUE;
    }

    if ($fieldName == 'revision_log') {
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

    // If the entity does not have the prison owner field, we allow editing.
    // This could of course be restricted by other Drupal permissions.
    if (!$entity->hasField($this->prisonOwnerFieldName)) {
      return TRUE;
    }

    $content_prisons = $entity->hasField($this->prisonOwnerFieldName) ? $entity->get($this->prisonOwnerFieldName)->referencedEntities() : [];
    // If the content is not owned by any prison, then deny access.
    if (empty($content_prisons)) {
      return FALSE;
    }

    // Only grant permission via prison if either the content is in the default
    // language, or the user explicitly has the permission to edit translated
    // content.
    if ($entity->language()->isDefault()
      || $this->user->hasPermission('translate editable entities')) {
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
          // Check if $content_prison is a parent category, and matches the
          // parent of $user_prison.
          // E.g. $content_prison = "Adult male", $user_prison = "Berwyn".
          foreach ($user_prison->get('parent') as $user_prison_parent) {
            if ($user_prison_parent->target_id == $content_prison->id()) {
              return TRUE;
            }
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
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown if the entity type doesn't exist.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown if the storage handler couldn't be loaded.
   */
  public function hasPrisonTermAccess(int $id) {
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    /** @var \Drupal\taxonomy\TermInterface $user_prison_term */
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
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   The prison entities assigned to the user.
   */
  public function getUserPrisons() {
    return $this->userPrisons;
  }

}
