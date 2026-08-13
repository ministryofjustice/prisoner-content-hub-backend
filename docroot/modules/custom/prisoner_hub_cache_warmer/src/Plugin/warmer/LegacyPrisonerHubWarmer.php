<?php

namespace Drupal\prisoner_hub_cache_warmer\Plugin\warmer;

use Drupal\taxonomy\TermInterface;

/**
 * Warmer for each prison.
 *
 * @Warmer(
 *   id = "prisoner_hub",
 *   label = @Translation("Prisoner Hub (Legacy)"),
 *   description = @Translation("Makes page cache requests for each prison based on the old JS front end.")
 * )
 */
class LegacyPrisonerHubWarmer extends PrisonerHubWarmerBase {

  /**
   * {@inheritdoc}
   */
  protected function getExcludedPrisons() {
    return ['thestudio'];
  }

  /**
   * {@inheritdoc}
   */
  protected function getPrimaryNavigationQuery() : string {
    return 'primary_navigation?fields%5Bmenu_link_content--menu_link_content%5D=id%2Ctitle%2Curl';
  }

  /**
   * {@inheritdoc}
   */
  protected function getRecentlyAddedQuery(): string {
    return 'recently-added?include=field_moj_thumbnail_image&sort=-published_at%2Ccreated&fields%5Bnode--page%5D=drupal_internal__nid%2Ctitle%2Cfield_moj_thumbnail_image%2Cfield_summary%2Cfield_moj_series%2Cpath%2Ctype.meta.drupal_internal__target_id%2Cpublished_at&fields%5Bnode--moj_video_item%5D=drupal_internal__nid%2Ctitle%2Cfield_moj_thumbnail_image%2Cfield_summary%2Cfield_moj_series%2Cpath%2Ctype.meta.drupal_internal__target_id%2Cpublished_at&fields%5Bnode--moj_radio_item%5D=drupal_internal__nid%2Ctitle%2Cfield_moj_thumbnail_image%2Cfield_summary%2Cfield_moj_series%2Cpath%2Ctype.meta.drupal_internal__target_id%2Cpublished_at&fields%5Bnode--moj_pdf_item%5D=drupal_internal__nid%2Ctitle%2Cfield_moj_thumbnail_image%2Cfield_summary%2Cfield_moj_series%2Cpath%2Ctype.meta.drupal_internal__target_id%2Cpublished_at&fields%5Bfile--file%5D=drupal_internal__fid%2Cid%2Cimage_style_uri&page[offset]=0&page[limit]=8';
  }

  /**
   * {@inheritdoc}
   */
  protected function getUpdatesQuery(int $earliest_published_date): string {
    return "node?filter%5B6%5D%5Bcondition%5D%5Bpath%5D=published_at&filter%5B6%5D%5Bcondition%5D%5Bvalue%5D=$earliest_published_date&filter%5B6%5D%5Bcondition%5D%5Boperator%5D=%3E%3D&filter%5B6%5D%5Bcondition%5D%5BmemberOf%5D=series_group&filter%5Bparent_or_group%5D%5Bgroup%5D%5Bconjunction%5D=OR&filter%5Bcategories_group%5D%5Bgroup%5D%5Bconjunction%5D=AND&filter%5Bcategories_group%5D%5Bgroup%5D%5BmemberOf%5D=parent_or_group&filter%5Bseries_group%5D%5Bgroup%5D%5Bconjunction%5D=AND&filter%5Bseries_group%5D%5Bgroup%5D%5BmemberOf%5D=parent_or_group&filter%5Bfield_moj_top_level_categories.field_is_homepage_updates%5D%5Bcondition%5D%5Bpath%5D=field_moj_top_level_categories.field_is_homepage_updates&filter%5Bfield_moj_top_level_categories.field_is_homepage_updates%5D%5Bcondition%5D%5Bvalue%5D=1&filter%5Bfield_moj_top_level_categories.field_is_homepage_updates%5D%5Bcondition%5D%5BmemberOf%5D=categories_group&filter%5Bpublished_at%5D%5Bcondition%5D%5Bpath%5D=published_at&filter%5Bpublished_at%5D%5Bcondition%5D%5Bvalue%5D=$earliest_published_date&filter%5Bpublished_at%5D%5Bcondition%5D%5Boperator%5D=%3E%3D&filter%5Bpublished_at%5D%5Bcondition%5D%5BmemberOf%5D=categories_group&filter%5Bfield_moj_series.field_is_homepage_updates%5D%5Bcondition%5D%5Bpath%5D=field_moj_series.field_is_homepage_updates&filter%5Bfield_moj_series.field_is_homepage_updates%5D%5Bcondition%5D%5Bvalue%5D=1&filter%5Bfield_moj_series.field_is_homepage_updates%5D%5Bcondition%5D%5BmemberOf%5D=series_group&include=field_moj_thumbnail_image&sort=-published_at%2Ccreated&fields%5Bnode--page%5D=drupal_internal__nid%2Ctitle%2Cfield_moj_thumbnail_image%2Cfield_summary%2Cfield_moj_series%2Cpath%2Ctype.meta.drupal_internal__target_id%2Cpublished_at&fields%5Bnode--moj_video_item%5D=drupal_internal__nid%2Ctitle%2Cfield_moj_thumbnail_image%2Cfield_summary%2Cfield_moj_series%2Cpath%2Ctype.meta.drupal_internal__target_id%2Cpublished_at&fields%5Bnode--moj_radio_item%5D=drupal_internal__nid%2Ctitle%2Cfield_moj_thumbnail_image%2Cfield_summary%2Cfield_moj_series%2Cpath%2Ctype.meta.drupal_internal__target_id%2Cpublished_at&fields%5Bnode--moj_pdf_item%5D=drupal_internal__nid%2Ctitle%2Cfield_moj_thumbnail_image%2Cfield_summary%2Cfield_moj_series%2Cpath%2Ctype.meta.drupal_internal__target_id%2Cpublished_at&fields%5Bfile--file%5D=drupal_internal__fid%2Cid%2Cimage_style_uri&page[offset]=0&page[limit]=5";
  }

  /**
   * {@inheritdoc}
   */
  protected function getUrgentBannersQuery(): string {
    return 'node/urgent_banner?include=field_more_info_page&fields%5Bnode--urgent_banner%5D=drupal_internal__nid%2Ctitle%2Ccreated%2Cchanged%2Cfield_more_info_page%2Cunpublish_on';
  }

  /**
   * {@inheritdoc}
   */
  protected function warmSeriesPage(string $prison, string $uuid) {
    $this->queueAsynchronousJsonApiRequest($prison, "node?filter%5Bfield_moj_series.id%5D=$uuid&include=field_moj_thumbnail_image%2Cfield_moj_series.field_moj_thumbnail_image&sort=series_sort_value%2Ccreated&fields%5Bnode--page%5D=drupal_internal__nid%2Ctitle%2Cfield_summary%2Cfield_moj_thumbnail_image%2Cfield_moj_series%2Cpath%2Cpublished_at&fields%5Bnode--moj_video_item%5D=drupal_internal__nid%2Ctitle%2Cfield_summary%2Cfield_moj_thumbnail_image%2Cfield_moj_series%2Cpath%2Cpublished_at&fields%5Bnode--moj_radio_item%5D=drupal_internal__nid%2Ctitle%2Cfield_summary%2Cfield_moj_thumbnail_image%2Cfield_moj_series%2Cpath%2Cpublished_at&fields%5Bnode--moj_pdf_item%5D=drupal_internal__nid%2Ctitle%2Cfield_summary%2Cfield_moj_thumbnail_image%2Cfield_moj_series%2Cpath%2Cpublished_at&fields%5Bfile--file%5D=image_style_uri&fields%5Btaxonomy_term--series%5D=name%2Cdescription%2Cdrupal_internal__tid%2Cfield_moj_thumbnail_image%2Cpath%2Cfield_exclude_feedback%2Cbreadcrumbs&page[offset]=0&page[limit]=40");
  }

  /**
   * {@inheritdoc}
   */
  protected function warmTopicPage(string $prison, TermInterface $term) {
    $uuid = $term->uuid();
    $this->queueAsynchronousJsonApiRequest($prison, "node?filter%5Bfield_topics.id%5D=$uuid&include=field_moj_thumbnail_image%2Cfield_topics.field_moj_thumbnail_image&sort=-created&fields%5Bnode--page%5D=drupal_internal__nid%2Ctitle%2Cfield_summary%2Cfield_moj_thumbnail_image%2Cfield_topics%2Cpath%2Cpublished_at&fields%5Bnode--moj_video_item%5D=drupal_internal__nid%2Ctitle%2Cfield_summary%2Cfield_moj_thumbnail_image%2Cfield_topics%2Cpath%2Cpublished_at&fields%5Bnode--moj_radio_item%5D=drupal_internal__nid%2Ctitle%2Cfield_summary%2Cfield_moj_thumbnail_image%2Cfield_topics%2Cpath%2Cpublished_at&fields%5Bnode--moj_pdf_item%5D=drupal_internal__nid%2Ctitle%2Cfield_summary%2Cfield_moj_thumbnail_image%2Cfield_topics%2Cpath%2Cpublished_at&fields%5Bfile--file%5D=image_style_uri&fields%5Btaxonomy_term--topics%5D=name%2Cdescription%2Cdrupal_internal__tid%2Cfield_moj_thumbnail_image%2Cpath%2Cfield_exclude_feedback%2Cbreadcrumbs&page[offset]=0&page[limit]=40");
  }

  /**
   * {@inheritdoc}
   */
  protected function warmCategoryPage(string $prison, TermInterface $term) {
    $uuid = $term->uuid();
    $this->queueAsynchronousJsonApiRequest($prison, "node?filter%5Bfield_moj_top_level_categories.id%5D=$uuid&include=field_moj_thumbnail_image&sort=-created&fields%5Bnode--page%5D=drupal_internal__nid%2Ctitle%2Cfield_summary%2Cfield_moj_thumbnail_image%2Cpath%2Cpublished_at&fields%5Bnode--moj_video_item%5D=drupal_internal__nid%2Ctitle%2Cfield_summary%2Cfield_moj_thumbnail_image%2Cpath%2Cpublished_at&fields%5Bnode--moj_radio_item%5D=drupal_internal__nid%2Ctitle%2Cfield_summary%2Cfield_moj_thumbnail_image%2Cpath%2Cpublished_at&fields%5Bmoj_pdf_item%5D=drupal_internal__nid%2Ctitle%2Cfield_summary%2Cfield_moj_thumbnail_image%2Cpath%2Cpublished_at&page[offset]=0&page[limit]=40");
    $this->queueAsynchronousJsonApiRequest($prison, "taxonomy_term/moj_categories/$uuid/sub_terms?include=field_moj_thumbnail_image&fields%5Btaxonomy_term--series%5D=type%2Cdrupal_internal__tid%2Cname%2Cfield_moj_thumbnail_image%2Cpath%2Ccontent_updated%2Cchild_term_count%2Cpublished_at&fields%5Btaxonomy_term--moj_categories%5D=type%2Cdrupal_internal__tid%2Cname%2Cfield_moj_thumbnail_image%2Cpath%2Ccontent_updated%2Cchild_term_count%2Cpublished_at&page[offset]=0&page[limit]=40");
    $this->queueAsynchronousJsonApiRequest($prison, "taxonomy_term/moj_categories/$uuid?include=field_featured_tiles%2Cfield_featured_tiles.field_moj_thumbnail_image&fields%5Bnode--page%5D=drupal_internal__nid%2Cdrupal_internal__tid%2Ctitle%2Cfield_moj_thumbnail_image%2Cfield_topics%2Cpath%2Cfield_exclude_feedback%2Cpublished_at&fields%5Bnode--moj_video_item%5D=drupal_internal__nid%2Cdrupal_internal__tid%2Ctitle%2Cfield_moj_thumbnail_image%2Cfield_topics%2Cpath%2Cfield_exclude_feedback%2Cpublished_at&fields%5Bnode--moj_radio_item%5D=drupal_internal__nid%2Cdrupal_internal__tid%2Ctitle%2Cfield_moj_thumbnail_image%2Cfield_topics%2Cpath%2Cfield_exclude_feedback%2Cpublished_at&fields%5Bmoj_pdf_item%5D=drupal_internal__nid%2Cdrupal_internal__tid%2Ctitle%2Cfield_moj_thumbnail_image%2Cfield_topics%2Cpath%2Cfield_exclude_feedback%2Cpublished_at&fields%5Btaxonomy_term_series%5D=drupal_internal__nid%2Cdrupal_internal__tid%2Ctitle%2Cfield_moj_thumbnail_image%2Cfield_topics%2Cpath%2Cfield_exclude_feedback%2Cpublished_at&fields%5Btaxonomy_term--moj_categories%5D=name%2Cdescription%2Cfield_exclude_feedback%2Cfield_featured_tiles%2Cbreadcrumbs%2Cchild_term_count");
  }

}
