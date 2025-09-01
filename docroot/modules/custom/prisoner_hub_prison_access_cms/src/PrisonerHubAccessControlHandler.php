<?php

namespace Drupal\prisoner_hub_prison_access_cms;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeAccessControlHandler;
use Drupal\node\NodeGrantDatabaseStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class to restrict who can edit translations.
 *
 * Users can edit any prison's content according to the node access system.
 * However, we disable nearly all fields when editing content from another
 * prison to the current user. This allows users to edit the exclude from
 * prison field only.
 *
 * However, because we use the `translate editable entities` permission to
 * control access for translations, this would allow users to edit translated
 * content from other prisons. We don't want to allow that, so we provide
 * an alternative node access control handler, and
 * implement hook_entity_type_alter() to force nodes to use this handler
 * instead of the core one.
 */
class PrisonerHubAccessControlHandler extends NodeAccessControlHandler {

  /**
   * Initializes an instance of the node access control handler.
   *
   * @param \Drupal\prisoner_hub_prison_access_cms\EntityEditAccess $entityEditAccess
   *   Entity edit access service.
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   Entity type.
   * @param \Drupal\node\NodeGrantDatabaseStorageInterface $grant_storage
   *   Grant storage service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   */
  public function __construct(
    protected EntityEditAccess $entityEditAccess,
    EntityTypeInterface $entity_type,
    NodeGrantDatabaseStorageInterface $grant_storage,
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    parent::__construct($entity_type, $grant_storage, $entity_type_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $container->get('prisoner_hub_prison_access_cms.entity_edit_access'),
      $entity_type,
      $container->get('node.grant_storage'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $node, $operation, AccountInterface $account) {
    $access = parent::checkAccess($node, $operation, $account);
    if ($operation == 'update'
      && $node instanceof ContentEntityInterface
      && !$access->isForbidden()
      && !$node->language()->isDefault()) {
      // According to normal rules, translation access would be allowed.
      // We need to double check against the user has either
      // `bypass prison ownership edit access`, or the content they are
      // editing belongs to the same prison as them.
      if ($this->entityEditAccess->hasEntityAccess($node) === FALSE) {
        return AccessResult::forbidden();
      }
    }
    return $access;
  }

}
