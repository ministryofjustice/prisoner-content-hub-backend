<?php

namespace Drupal\prisoner_hub_cache_warmer\Plugin\warmer;

/**
 * Warmer for each prison.
 *
 * @Warmer(
 *   id = "prisoner_hub_ts",
 *   label = @Translation("Prisoner Hub"),
 *   description = @Translation("Makes page cache requests for each prison.")
 * )
 */
class PrisonerHubWarmer extends PrisonerHubWarmerBase {

  /**
   * {@inheritdoc}
   */
  protected function getExcludedPrisons() {
    return [
      'bedford',
      'berwyn',
      'bristol',
      'bullingdon',
      'cardiff',
      'chelmsford',
      'cookhamwood',
      'erlestoke',
      'felthama',
      'felthamb',
      'garth',
      'lindholme',
      'newhall',
      'ranby',
      'stokeheath',
      'styal',
      'swaleside',
      'themount',
      'wayland',
      'werrington',
      'wetherby',
      'woodhill',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function warmCategoryPage(string $prison, string $uuid) {
    // @todo Implement warmCategoryPage() method.
  }

  /**
   * {@inheritdoc}
   */
  protected function warmPrimaryNavigation(string $prison) {
    // @todo Implement warmPrimaryNavigation() method.
  }

  /**
   * {@inheritdoc}
   */
  protected function warmPrisonHomePage(string $prison) {
    // @todo Implement warmPrisonHomePage() method.
  }

  /**
   * {@inheritdoc}
   */
  protected function warmSeriesPage(string $prison, string $uuid) {
    // @todo Implement warmSeriesPage() method.
  }

  /**
   * {@inheritdoc}
   */
  protected function warmTopicPage(string $prison, string $uuid) {
    // @todo Implement warmTopicPage() method.
  }

}
