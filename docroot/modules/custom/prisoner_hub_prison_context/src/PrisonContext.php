<?php

namespace Drupal\prisoner_hub_prison_context;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\ParamConverter\ParamConverterInterface;
use Symfony\Component\Routing\Route;

/**
 * Class PrisonContext.
 *
 * Param converter for converting prison machine names
 * in paths to fully loaded taxonomy terms.
 */
class PrisonContext implements ParamConverterInterface {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new PrisonContext object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    if (!empty($value)) {
      return $this->getPrisonTerm($value);
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route) {
    return !empty($definition['type']) && $definition['type'] == 'prison_context';
  }

  /**
   * Load the prison term by name.
   *
   * @param string $name
   *   Machine name of prison term.
   *
   * @return \Drupal\core\entity\EntityInterface|null
   *   Term entity represented by the passed machine name, or null if it could
   *   not be loaded.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getPrisonTerm(string $name) {
    $tid = $this->entityTypeManager->getStorage('taxonomy_term')
      ->getQuery()
      ->condition('machine_name', $name)
      ->accessCheck(TRUE)
      ->execute();

    if ($tid) {
      return $this->entityTypeManager->getStorage('taxonomy_term')->load(reset($tid));
    }
    else {
      return NULL;
    }
  }

}
