<?php

namespace Drupal\prisoner_hub_prison_access_cms;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\content_translation\ContentTranslationHandler;
use Drupal\content_translation\ContentTranslationManagerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class to restrict who can create translations.
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
   * Initializes an instance of the content translation handler.
   *
   * @param \Drupal\prisoner_hub_prison_access_cms\EntityEditAccess $entityEditAccess
   *   The entity edit access service.
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The info array of the given entity type.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\content_translation\ContentTranslationManagerInterface $manager
   *   The content translation manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Entity\EntityLastInstalledSchemaRepositoryInterface $entity_last_installed_schema_repository
   *   The installed entity definition repository service.
   * @param \Drupal\Core\Routing\RedirectDestinationInterface|null $redirectDestination
   *   The request stack.
   * @param \Drupal\Component\Datetime\TimeInterface|null $time
   *   The time service.
   */
  public function __construct(
    protected EntityEditAccess $entityEditAccess,
    EntityTypeInterface $entity_type,
    LanguageManagerInterface $language_manager,
    ContentTranslationManagerInterface $manager,
    EntityTypeManagerInterface $entity_type_manager,
    AccountInterface $current_user,
    MessengerInterface $messenger,
    DateFormatterInterface $date_formatter,
    EntityLastInstalledSchemaRepositoryInterface $entity_last_installed_schema_repository,
    ?RedirectDestinationInterface $redirectDestination = NULL,
    ?TimeInterface $time = NULL,
  ) {
    parent::__construct(
      $entity_type,
      $language_manager,
      $manager,
      $entity_type_manager,
      $current_user,
      $messenger,
      $date_formatter,
      $entity_last_installed_schema_repository,
      $redirectDestination,
      $time,
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $container->get('prisoner_hub_prison_access_cms.entity_edit_access'),
      $entity_type,
      $container->get('language_manager'),
      $container->get('content_translation.manager'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('messenger'),
      $container->get('date.formatter'),
      $container->get('entity.last_installed_schema.repository'),
      $container->get('redirect.destination'),
      $container->get('datetime.time'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getTranslationAccess(EntityInterface $entity, $op) {
    $access = parent::getTranslationAccess($entity, $op);
    if ($entity instanceof ContentEntityInterface && $access->isAllowed()) {
      // According to normal rules, translation access would be allowed.
      // We need to double check against the user has either
      // `bypass prison ownership edit access`, or the content they are
      // editing belongs to the same prison as them.
      if (!$this->entityEditAccess->hasEntityAccess($entity)) {
        return AccessResult::forbidden();
      }
    }
    return $access;
  }

}
