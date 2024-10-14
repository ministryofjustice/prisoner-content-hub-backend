<?php

namespace Drupal\Tests\prisoner_hub_test_traits\Traits;

use Drupal\node\NodeInterface;
use weitzman\DrupalTestTraits\Entity\NodeCreationTrait as DrupalTestTraitsNodeCreationTrait;
use weitzman\DrupalTestTraits\Entity\TaxonomyCreationTrait;

/**
 * Trait for prisoner hub specific node creation methods.
 */
trait NodeCreationTrait {
  use DrupalTestTraitsNodeCreationTrait;
  use TaxonomyCreationTrait;

  /**
   * Creates a node with a random category.
   *
   * We have a constraint that certain types of node must have a category or
   * series. Usually in tests, we don't care which of the two it has, or which
   * specific series or category it is in. This utility function adds a
   * throwaway category to the node being created, to make sure the constraint
   * is not violated.
   *
   * @param array $settings
   *   An associative array of values for the node, as used in
   *   creation of entity.
   *   Note any array entry keyed field_moj_top_level_categories will be
   *   ignored.
   *
   * @return \Drupal\node\NodeInterface
   *   Created node.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function createCategorisedNode(array $settings = []): NodeInterface {
    /** @var \Drupal\taxonomy\VocabularyInterface $category_vocabulary */
    $category_vocabulary = \Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary')->load('moj_categories');
    $category = $this->createTerm($category_vocabulary);
    $settings['field_moj_top_level_categories'] = [['target_id' => $category->id()]];
    return $this->createNode($settings);
  }

}
