<?php

namespace Drupal\prisoner_hub_prison_access_cms;

use Drupal\content_translation\ContentTranslationHandler;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;

/**
 * Class to restrict who can edit translations.
 *
 * Users can edit any prison's content according to the node access system.
 * However, we disable nearly all fields when editing content from another
 * prison to the current user. This allows users to edit the exclude from
 * prison field only.
 *
 * However, because we use the `translate editable entities` permission to
 * control access for translations, this would allow users to translate
 * content from other prisons. We don't want to allow that, so we provide
 * an alternative translation handler, and implement hook_entity_type_alter()
 * to force nodes to use this handler instead of the core one.
 */
class PrisonerHubContentTranslationHandler extends ContentTranslationHandler {

  /**
   * {@inheritdoc}
   */
  public function getTranslationAccess(EntityInterface $entity, $op) {
    $access = parent::getTranslationAccess($entity, $op);
    if ($access->isAllowed()) {
      // According to normal rules, translation access would be allowed.
      // We need to double check against the user has either
      // `bypass prison ownership edit access`, or the content they are
      // editing belongs to the same prison as them.
      $access = AccessResult::allowedIf(\Drupal::service('prisoner_hub_prison_access_cms.entity_edit_access')->hasEntityAccess($entity))->cachePerUser();
    }
    return $access;
  }

}
