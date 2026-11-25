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
      if ($entity->bundle() == 'topics') {
        $this->addTopicBreadcrumbs();
        return;
      }
      if ($entity->bundle() == 'series') {
        $this->addSeriesBreadcrumbs($entity);
        return;
      }
    }
  }

  private function addHomeBreadcrumb() {
    $this->list[] = $this->createItem(0, [
      'uri' => "/en",
      'title' => $this->t('Home'),
    ]);
  }

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
    $parents = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadAllParents($category->id());
    $breadcrumb_terms = array_reverse($parents);
    if ($series) {
      $breadcrumb_terms[] = $series;
    }
    foreach ($breadcrumb_terms as $term) {
      $term = \Drupal::service('entity.repository')->getTranslationFromContext($term);
      $this->list[] = $this->createItem(0, [
        'uri' => "/en/tags/" . $term->id(),
        'title' => $term->getName(),
      ]);
    }

  }

  private function addTopicBreadcrumbs() {
    $this->addHomeBreadcrumb();

    $this->list[] = $this->createItem(1, [
      'uri' => "/topics",
      'title' => $this->t('Browse all topics'),
    ]);
  }

  private function addSeriesBreadcrumbs(TermInterface $term) {
    $this->addHomeBreadcrumb();

    $categories = $term->get('field_category')->referencedEntities();

    // If no categories found, return the breadcrumb with just "Home" link.
    if (empty($categories)) {
      return;
    }

    /** @var \Drupal\taxonomy\TermInterface $category */
    $category = reset($categories);
    $parents = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadAllParents($category->id());
    foreach (array_reverse($parents) as $term) {
      $term = \Drupal::service('entity.repository')->getTranslationFromContext($term);
      $this->list[] = $this->createItem(0, [
        'uri' => "/en/tags/" . $term->id(),
        'title' => $term->getName(),
      ]);
    }

  }

}
