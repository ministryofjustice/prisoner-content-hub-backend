<?php
namespace Drupal\prisoner_hub_taxonomy_sorting\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\taxonomy\TermInterface;
use Drupal\views\Views;

/**
 * Provides route responses for the prisoner_hub_taxonomy_sorting module.
 */
class PageController extends ControllerBase {

  /**
   * Returns a simple page.
   *
   * @return array
   *   A simple renderable array.
   */
  public function renderPage(TermInterface $taxonomy_term) {
    if (in_array($taxonomy_term->bundle(), $this->getBundles())) {
      $view_name = 'series_taxonomy_term_content_sorting';
      $sort_by_field_value = $taxonomy_term->get('field_sort_by')->getValue();
      switch ($sort_by_field_value[0]['value']) {
        case 'season_and_episode_asc':
          $view_display_id = 'embed_2';
          break;

        case 'release_date_desc':
          $view_display_id = 'embed_3';
          break;

        case 'release_date_asc':
          $view_display_id = 'embed_4';
          break;

        case 'season_and_episode_desc':
        default:
          $view_display_id = 'embed_1';
      }

      $view = Views::getView($view_name);

      return $view->buildRenderable($view_display_id, [$taxonomy_term->id()]);
    }

  }

  /**
   * {@inheritdoc}
   */
  public function titleCallback(TermInterface $taxonomy_term) {
    return $this->t('%label: Change episode order', ['%label' => $taxonomy_term->label()]);
  }

  /**
   * Hide the content sorting tab on Taxonomy terms where we don't apply it.
   */
  public function access(TermInterface $taxonomy_term) {
    return AccessResult::allowedIf(in_array($taxonomy_term->bundle(), $this->getBundles()));
  }

  /**
   * Get the Taxonomy bundles (aka vocabularies) to apply content sorting page to.
   *
   * @return Array
   *   A list of bundles to apply content sorting page to.
   */
  public function getBundles() {
    // TODO: Get this from configuration or container parameters.
    return ['series'];
  }
}
