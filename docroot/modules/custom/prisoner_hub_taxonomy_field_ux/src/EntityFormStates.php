<?php

namespace Drupal\prisoner_hub_taxonomy_field_ux;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * A service to add conditional states to taxonomy fields.
 *
 * For info on the API used within this service, see
 * https://www.drupal.org/docs/drupal-apis/form-api/conditional-form-fields.
 */
class EntityFormStates {

  /**
   * An array of Drupal conditional form states.
   *
   * These are based on field_moj_series being selected with a series that has
   * season+episode sorting.
   *
   * @var array
   */
  protected $episodeSortingStates;

  /**
   * An array of Drupal conditional form states.
   *
   * These are based on field_moj_series being selected with a series that has
   * release date sorting.
   *
   * @var array
   */
  protected $releaseDateSortingStates;

  /**
   * Constructs a new EntityFormStates object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager) {
    $this->generateStatesForTermsWithSorting();
  }

  /**
   * Generate $this->episodeSortingStates and $this->releaseDateSortingState.
   *
   * These are to be used as #states.
   */
  protected function generateStatesForTermsWithSorting() {
    $this->episodeSortingStates = [];
    $this->releaseDateSortingStates = [];

    $result = $this->entityTypeManager->getStorage('taxonomy_term')
      ->getQuery()
      ->accessCheck(TRUE)
      ->exists('field_sort_by')
      ->execute();
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $terms = $term_storage->loadMultiple($result);
    foreach ($terms as $term) {
      /** @var \Drupal\taxonomy\TermInterface $term */
      $sort_by_value = $term->get('field_sort_by')->getValue();
      if (in_array($sort_by_value[0]['value'], [
        'season_and_episode_desc',
        'season_and_episode_asc',
      ])) {
        $this->episodeSortingStates[] = ['value' => $term->id()];
      }
      elseif (in_array($sort_by_value[0]['value'], [
        'release_date_desc',
        'release_date_asc',
      ])) {
        $this->releaseDateSortingStates[] = ['value' => $term->id()];
      }
    }
  }

  /**
   * Apply conditional form states to the Drupal $form array.
   *
   * Note this is designed to be called from some kind of hook_form_alter().
   *
   * @param array $form
   *   Form to which additional states are being applied.
   */
  public function applyToForm(array &$form) {
    if (empty($this->episodeSortingStates)) {
      hide($form['group_season_and_episode_number']);
    }
    else {
      $form['group_season_and_episode_number']['#states']['visible'] = [
        ':input[name="field_moj_series"]' => $this->episodeSortingStates,
      ];
      $form['field_moj_season']['widget'][0]['value']['#states']['required'] = [
        ':input[name="field_moj_series"]' => $this->episodeSortingStates,
        0 => 'and',
        ':input[name="field_not_in_series[value]"]' => ['checked' => FALSE],
      ];
      $form['field_moj_episode']['widget'][0]['value']['#states']['required'] = [
        ':input[name="field_moj_series"]' => $this->episodeSortingStates,
        0 => 'and',
        ':input[name="field_not_in_series[value]"]' => ['checked' => FALSE],
      ];
    }

    if (empty($this->releaseDateSortingStates)) {
      hide($form['group_release_date']);
    }
    else {
      $form['group_release_date']['#states']['visible'] = [
        ':input[name="field_moj_series"]' => $this->releaseDateSortingStates,
      ];
      // Currently we cannot set a required state for field_release_date.
      // See https://www.drupal.org/project/drupal/issues/2419131
      // However, this field has a default value (of the current date),
      // so it's less likely to not be set.
    }

    // Apply states to the category field and group, based on
    // field_not_in_series.
    $form['group_category']['#states']['visible'][':input[name="field_not_in_series[value]"]']['checked'] = TRUE;
    $form['field_moj_top_level_categories']['widget']['#states']['required'][':input[name="field_not_in_series[value]"]']['checked'] = TRUE;
    $form['field_moj_top_level_categories']['widget']['#states']['empty'][':input[name="field_not_in_series[value]"]']['checked'] = FALSE;

    // Apply states to the series field and group, based on field_not_in_series.
    $form['field_moj_series']['#states']['visible'][':input[name="field_not_in_series[value]"]']['checked'] = FALSE;
    $form['field_moj_series']['widget']['#states']['required'][':input[name="field_not_in_series[value]"]']['checked'] = FALSE;
    $form['field_moj_series']['#states']['empty'][':input[name="field_not_in_series[value]"]']['checked'] = TRUE;
  }

}
