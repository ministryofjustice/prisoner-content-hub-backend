<?php

namespace Drupal\prisoner_hub_breadcrumbs\Field;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\Node\NodeInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Provides a computed breadcrumbs field without using the contrib module.
 */
class ComputedBreadcrumbsItemList extends FieldItemList {
  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    // @todo computed_breadcrumbs module doesn't reset this. Why?
    $this->list = [];

    $entity = $this->getEntity();
    // If this is a node that can have a series or category, and at least one
    // of them is set..
    if ($entity instanceof NodeInterface
        && $entity->hasField('field_moj_top_level_categories')
        && $entity->hasField('field_moj_series')
        && (!empty($entity->get('field_moj_top_level_categories')->getValue())
          || !empty($entity->get('field_moj_series')->getValue()))
    ) {
      // ..add the node breadcrumbs.
      $this->addNodeBreadcrumbs($entity);
      return;
    }
    if ($entity instanceof TermInterface) {
      match ($entity->bundle()) {
        'topics' => $this->addTopicBreadcrumbs(),
        'series' => $this->addSeriesBreadcrumbs($entity),
        'moj_categories' => $this->addCategoryBreadcrumbs($entity),
        default => NULL,
      };
    }
  }

  /**
   * Add the home breadcrumb.
   */
  private function addHomeBreadcrumb() {
    $this->list[] = $this->createItem(count($this->list), [
      'uri' => "/en",
      'title' => $this->t('Home'),
    ]);
  }

  /**
   * Add all required breadcrumbs for a node.
   *
   * Assumes node is of type that can have a series or category.
   *
   * @param \Drupal\Node\NodeInterface $node
   *   Node for which to add breadcrumbs.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function addNodeBreadcrumbs(NodeInterface $node) {
    $this->addHomeBreadcrumb();

    $series_value = $node->get('field_moj_series')->referencedEntities();
    $series = NULL;
    if (!empty($series_value)) {
      /** @var \Drupal\taxonomy\TermInterface $series */
      $series = reset($series_value);
      $categories = $series->get('field_category')->referencedEntities();
    }
    else {
      $categories = $node->get('field_moj_top_level_categories')->referencedEntities();
    }

    // If no categories found, return the breadcrumb with just "Home" link.
    if (empty($categories)) {
      return;
    }

    /** @var \Drupal\taxonomy\TermInterface $category */
    $category = reset($categories);
    $this->addParentTerms($category, FALSE);
    if ($series) {
      $this->list[] = $this->createItem(count($this->list), [
        'uri' => "/en/tags/" . $series->id(),
        'title' => $series->getName(),
      ]);
    }

  }

  /**
   * Adds all required breadcrumbs for a topic.
   *
   * These are the same regardless of the specfic topic, as topics are not in
   * a hierarchy.
   */
  private function addTopicBreadcrumbs() {
    $this->addHomeBreadcrumb();

    $this->list[] = $this->createItem(count($this->list), [
      'uri' => "/topics",
      'title' => $this->t('Browse all topics'),
    ]);
  }

  /**
   * Adds all required breadcrumbs for a series.
   *
   * @param \Drupal\taxonomy\TermInterface $term
   *   The series for which to add breadcrumbs.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function addSeriesBreadcrumbs(TermInterface $term) {
    $this->addHomeBreadcrumb();

    $categories = $term->get('field_category')->referencedEntities();

    // If no categories found, return the breadcrumb with just "Home" link.
    if (empty($categories)) {
      return;
    }

    /** @var \Drupal\taxonomy\TermInterface $category */
    $category = reset($categories);
    $this->addParentTerms($category, FALSE);
  }

  /**
   * Adds all required breadcrumbs for a category.
   *
   * @param \Drupal\taxonomy\TermInterface $term
   *   The category for which to add breadcrumbs.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function addCategoryBreadcrumbs(TermInterface $term) {
    $this->addHomeBreadcrumb();
    $this->addParentTerms($term);
  }

  /**
   * Adds parent terms to the breadcrumbs list.
   *
   * Optionally excludes the passed term from that list.
   *
   * @param \Drupal\taxonomy\TermInterface $term
   *   The term whose parents to add.
   * @param bool $exclude_self
   *   Whether to exclude the passed term from the list.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function addParentTerms(TermInterface $term, bool $exclude_self = TRUE) {
    $parent_terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadAllParents($term->id());
    if ($exclude_self && array_key_exists($term->id(), $parent_terms)) {
      unset($parent_terms[$term->id()]);
    }
    foreach (array_reverse($parent_terms) as $parent_term) {
      $parent_term = \Drupal::service('entity.repository')->getTranslationFromContext($parent_term);
      $this->list[] = $this->createItem(count($this->list), [
        'uri' => "/en/tags/" . $parent_term->id(),
        'title' => $parent_term->getName(),
      ]);
    }

  }

}
