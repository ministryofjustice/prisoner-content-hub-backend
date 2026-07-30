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
  protected function getPrimaryNavigationQuery() : string {
    return "primary_navigation?fields%5Bmenu_link_content--menu_link_content%5D=title%2Curl";
  }

  /**
   * {@inheritdoc}
   */
  protected function getRecentlyAddedQuery(): string {
    return 'recently-added?include=field_moj_thumbnail_image&page%5Blimit%5D=8&page%5Boffset%5D=0&sort=-published_at%2Ccreated&fields%5Bnode--page%5D=drupal_internal__nid%2Ctitle%2Cfield_moj_thumbnail_image%2Cfield_summary%2Cfield_moj_series%2Cpath%2Ctype.meta.drupal_internal__target_id%2Cpublished_at&fields%5Bnode--moj_video_item%5D=drupal_internal__nid%2Ctitle%2Cfield_moj_thumbnail_image%2Cfield_summary%2Cfield_moj_series%2Cpath%2Ctype.meta.drupal_internal__target_id%2Cpublished_at&fields%5Bnode--moj_radio_item%5D=drupal_internal__nid%2Ctitle%2Cfield_moj_thumbnail_image%2Cfield_summary%2Cfield_moj_series%2Cpath%2Ctype.meta.drupal_internal__target_id%2Cpublished_at&fields%5Bnode--moj_pdf_item%5D=drupal_internal__nid%2Ctitle%2Cfield_moj_thumbnail_image%2Cfield_summary%2Cfield_moj_series%2Cpath%2Ctype.meta.drupal_internal__target_id%2Cpublished_at&fields%5Bfile--file%5D=drupal_internal__fid%2Cid%2Cimage_style_uri';
  }

  /**
   * {@inheritdoc}
   */
  protected function getUpdatesQuery(int $earliest_published_date): string {
    return "node?filter%5B6%5D%5Bcondition%5D%5Bpath%5D=published_at&filter%5B6%5D%5Bcondition%5D%5Bvalue%5D=$earliest_published_date&filter%5B6%5D%5Bcondition%5D%5Boperator%5D=%3E%3D&filter%5B6%5D%5Bcondition%5D%5BmemberOf%5D=series_group&filter%5Bparent_or_group%5D%5Bgroup%5D%5Bconjunction%5D=OR&filter%5Bcategories_group%5D%5Bgroup%5D%5Bconjunction%5D=AND&filter%5Bcategories_group%5D%5Bgroup%5D%5BmemberOf%5D=parent_or_group&filter%5Bseries_group%5D%5Bgroup%5D%5Bconjunction%5D=AND&filter%5Bseries_group%5D%5Bgroup%5D%5BmemberOf%5D=parent_or_group&filter%5Bfield_moj_top_level_categories.field_is_homepage_updates%5D%5Bcondition%5D%5Bpath%5D=field_moj_top_level_categories.field_is_homepage_updates&filter%5Bfield_moj_top_level_categories.field_is_homepage_updates%5D%5Bcondition%5D%5Bvalue%5D=1&filter%5Bfield_moj_top_level_categories.field_is_homepage_updates%5D%5Bcondition%5D%5BmemberOf%5D=categories_group&filter%5Bpublished_at%5D%5Bcondition%5D%5Bpath%5D=published_at&filter%5Bpublished_at%5D%5Bcondition%5D%5Bvalue%5D=1777590000&filter%5Bpublished_at%5D%5Bcondition%5D%5Boperator%5D=%3E%3D&filter%5Bpublished_at%5D%5Bcondition%5D%5BmemberOf%5D=categories_group&filter%5Bfield_moj_series.field_is_homepage_updates%5D%5Bcondition%5D%5Bpath%5D=field_moj_series.field_is_homepage_updates&filter%5Bfield_moj_series.field_is_homepage_updates%5D%5Bcondition%5D%5Bvalue%5D=1&filter%5Bfield_moj_series.field_is_homepage_updates%5D%5Bcondition%5D%5BmemberOf%5D=series_group&include=field_moj_thumbnail_image&page%5Blimit%5D=5&page%5Boffset%5D=0&sort=-published_at%2Ccreated&fields%5Bnode--page%5D=drupal_internal__nid%2Ctitle%2Cfield_moj_thumbnail_image%2Cfield_summary%2Cfield_moj_series%2Cpath%2Ctype.meta.drupal_internal__target_id%2Cpublished_at&fields%5Bnode--moj_video_item%5D=drupal_internal__nid%2Ctitle%2Cfield_moj_thumbnail_image%2Cfield_summary%2Cfield_moj_series%2Cpath%2Ctype.meta.drupal_internal__target_id%2Cpublished_at&fields%5Bnode--moj_radio_item%5D=drupal_internal__nid%2Ctitle%2Cfield_moj_thumbnail_image%2Cfield_summary%2Cfield_moj_series%2Cpath%2Ctype.meta.drupal_internal__target_id%2Cpublished_at&fields%5Bnode--moj_pdf_item%5D=drupal_internal__nid%2Ctitle%2Cfield_moj_thumbnail_image%2Cfield_summary%2Cfield_moj_series%2Cpath%2Ctype.meta.drupal_internal__target_id%2Cpublished_at&fields%5Bfile--file%5D=drupal_internal__fid%2Cid%2Cimage_style_uri";
  }

  /**
   * {@inheritdoc}
   */
  protected function getUrgentBannersQuery(): string {
    return 'node/urgent_banner?include=field_more_info_page&fields%5Bnode--urgent_banner%5D=title%2Cfield_more_info_page%2Cunpublish_on';
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
