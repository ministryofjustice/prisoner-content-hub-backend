<?php

namespace Drupal\prisoner_hub_entity_access;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Session\AccountInterface;
use Drupal\jsonapi\Controller\EntityResource;
use Drupal\node\NodeAccessControlHandler;
use LogicException;

/**
 * Drupal service to be used in conjunction with hook_entity_access().
 *
 * Note that hook_entity_access() only works on fully loaded entities, and not
 * when querying for a list of entities (e.g. via an entity query).
 */
class EntityAccessCheck extends NodeAccessControlHandler {

  /**
   * The prison category loader service.
   *
   * @var \Drupal\prisoner_hub_entity_access\PrisonCategoryLoader
   */
  protected $prisonCategoryLoader;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * SearchApiQueryAlter constructor.
   *
   * @param \Drupal\prisoner_hub_entity_access\PrisonCategoryLoader $prison_category_loader
   *   The PrisonCategoryLoader service.
   */
  //  public function __construct(EntityTypeManager $entity_type_manager) {
  //    $this->entityTypeManager = $entity_type_manager;
  //  }

  public function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    $access = parent::checkAccess($entity, $operation, $account);
    if ($access->isForbidden()) {
      return $access;
    }

    $entity_type = $entity->getEntityType();



    $cacheability = new CacheableMetadata();
    $query = \Drupal::entityTypeManager()->getStorage($entity->getEntityTypeId())->getQuery()->condition($entity_type->getKey('id'), $entity->id());
    $entity_ids = $this->executeQueryInRenderContext($query, $cacheability);
    $stop = 1;
    if (empty($entity_ids)) {
      return AccessResult::forbidden();
    }
    return AccessResult::allowed();
  }

  /**
   * Executes the query in a render context, to catch bubbled cacheability.
   *
   * @param \Drupal\Core\Entity\Query\QueryInterface $query
   *   The query to execute to get the return results.
   * @param \Drupal\Core\Cache\CacheableMetadata $query_cacheability
   *   The value object to carry the query cacheability.
   *
   * @return int|array
   *   Returns an integer for count queries or an array of IDs. The values of
   *   the array are always entity IDs. The keys will be revision IDs if the
   *   entity supports revision and entity IDs if not.
   *
   * @see node_query_node_access_alter()
   * @see https://www.drupal.org/project/drupal/issues/2557815
   * @see https://www.drupal.org/project/drupal/issues/2794385
   * @todo Remove this after https://www.drupal.org/project/drupal/issues/3028976 is fixed.
   */
  protected function executeQueryInRenderContext(QueryInterface $query, CacheableMetadata $query_cacheability) {
    return $this->executeInRenderContext(function () use ($query) {
      return $query->execute();
    }, $query_cacheability);
  }

  /**
   * Executes a callable in a render context.
   *
   * @param callable $f
   *   The function to execute in a render context.
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   The value object to carry the captured cacheability.
   *
   * @return mixed
   *   Returns the return value of the executed callable.
   *
   * @see node_query_node_access_alter()
   * @see https://www.drupal.org/project/drupal/issues/2557815
   * @see https://www.drupal.org/project/drupal/issues/2794385
   * @todo Remove this after https://www.drupal.org/project/drupal/issues/3028976 is fixed.
   */
  protected function executeInRenderContext(callable $f, CacheableMetadata $cacheability) {
    $context = new RenderContext();
    $ret = \Drupal::service('renderer')->executeInRenderContext($context, function () use ($f) {
      return $f();
    });
    if (!$context->isEmpty()) {
      $cacheability->addCacheableDependency($context->pop());
    }
    return $ret;
  }

  /**
   * Checks an entity and returns the appropriate AccessResult().
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity object, note that only content entities are checked.
   *
   * @return \Drupal\Core\Access\AccessResultReasonInterface
   *   The AccessResult, depending on the prison category filtering rule.
   */
  public function getAccessBasedOnPrisonCategory(EntityInterface $entity) {
    // Only deal with content entities.
    if (!$entity instanceof ContentEntityInterface) {
      return AccessResult::neutral();
    }
    $entity_type = $entity->getEntityType();
    try {
      $entity_ids = $this->entityTypeManager->getStorage($entity->getEntityTypeId())->getQuery()->condition($entity_type->getKey('id'), $entity->id())->execute();
      if (empty($entity_ids)) {
        return AccessResult::forbidden();
      }
    }
    catch (LogicException $e) {

    }

    return AccessResult::allowed();
  }
}
