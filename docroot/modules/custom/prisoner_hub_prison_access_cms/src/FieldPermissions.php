<?php

namespace Drupal\prisoner_hub_prison_access_cms;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\Entity\User;

/**
 * Class FieldPermissions.
 */
class FieldPermissions implements TrustedCallbackInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $user;

  /**
   * Constructs a new UserCacheContextBase class.
   *
   * @param \Drupal\user\UserInterface $user
   *   The current user.
   */
  public function __construct(AccountInterface $account, string $prison_owner_field_name, string $user_prison_field_name, string $exclude_from_prison_field_name) {
    $this->user = User::load($account->id());
    $this->prisonOwnerFieldName = $prison_owner_field_name;
    $this->userPrisonFieldName = $user_prison_field_name;
    $this->excludeFromPrisonFieldName = $exclude_from_prison_field_name;
    $this->userPrisons = $this->user->hasField($this->userPrisonFieldName) ? $this->user->get($this->userPrisonFieldName)->referencedEntities() : [];
  }

  /**
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *
   * @return bool
   */
  public function checkFieldAccess(FieldDefinitionInterface $fieldDefinition, ContentEntityInterface $entity) {
    if ($this->user->hasPermission('bypass node access')) {
      return TRUE;
    }

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

  public function setExcludeFromPrisonFieldAccess(&$element) {
    $element['#pre_render'][] = [$this, 'preRenderExcludeFromPrisonField'];
  }

  public static function trustedCallbacks() {
    return ['preRenderExcludeFromPrisonField'];
  }


  public function preRenderExcludeFromPrisonField($element) {
    foreach (Element::children($element) as $key) {
      foreach ($this->userPrisons as $user_prison_term) {
        if ($user_prison_term->id() == $key) {
          break;
        }
        $element[$key]['#attributes']['disabled'] = 'disabled';
      }
//      if (!in_array($key, $this->userPrisons)) {
//
//      }
      foreach (Element::children($element[$key]) as $child_key) {
        $element[$key][$child_key] = $this->preRenderExcludeFromPrisonField($element[$key][$child_key]);
      }
    }
    return $element;
  }

  public function excludeFromPrisonFieldSubmit($form, $form_state) {
    $sopt = 1;
  }

  public function setDefaultPrisonOwner(&$element) {
    /** @var \Drupal\taxonomy\TermInterface $user_prison */
    foreach ($this->userPrisons as $user_prison) {
      $element['#default_value'][]['target_id'] = $user_prison->id();
    }
  }

}
