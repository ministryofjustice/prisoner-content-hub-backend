<?php

namespace Drupal\prisoner_hub_taxonomy_field_ux;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\taxonomy\Entity\Term;

/**
 * A service that modifies a Drupal form to add conditional states that enhance
 * the UX of taxonomy fields.
 *
 * For info on the API used within this service, see https://www.drupal.org/docs/drupal-apis/form-api/conditional-form-fields
 */
class EntityFormStates {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * An array of Drupal conditional form states, based on field_moj_series
   * being selected with a series that has season+episode sorting..
   *
   * @var array
   */
  protected $episodeSortingStates;

  /**
   * An array of Drupal conditional form states, based on field_moj_series
   * being selected with a series that has release date sorting.
   *
   * @var array
   */
  protected $releaseDateSortingStates;

  /**
   * Constructs a new EntityFormStates object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->generateStatesForTermsWithSorting();
  }

  /**
   * Generate $this->episodeSortingStates and $this->releaseDateSortingState
   * to be used as #states.
   */
  protected function generateStatesForTermsWithSorting() {
    $this->episodeSortingStates = [];
    $this->releaseDateSortingStates = [];

    $query = $this->entityTypeManager->getStorage('taxonomy_term')->getQuery();
    $query->exists('field_sort_by');
    $result = $query->execute();
    $terms = Term::loadMultiple($result);
    foreach ($terms as $term) {
      /* @var \Drupal\taxonomy\TermInterface $term */
      $sort_by_value = $term->get('field_sort_by')->getValue();
      if (in_array($sort_by_value[0]['value'], ['season_and_episode_desc', 'season_and_episode_asc'])) {
        $this->episodeSortingStates[] = ['value' => $term->id()];
      }
      elseif (in_array($sort_by_value[0]['value'], ['release_date_desc', 'release_date_asc'])) {
        $this->releaseDateSortingStates[] = ['value' => $term->id()];
      }
    }
  }

  /**
   * Apply conditional form states to the Drupal $form array.
   *
   * Note this is designed to be called from somekind of hook_form_alter().
   *
   * @param array $form
   */
  public function applyToForm(array &$form) {
    if (empty($this->episodeSortingStates)) {
      hide($form['group_season_and_episode_number']);
    }
    else {
      $form['group_season_and_episode_number']['#states']['visible'][':input[name="field_moj_series"]'] = $this->episodeSortingStates;
      $form['field_moj_season']['widget'][0]['value']['#states']['required'] = [
        ':input[name="field_moj_series"]' => $this->episodeSortingStates,
        'and',
        ':input[name="field_not_in_series[value]"]' => ['checked' => FALSE],
      ];
      $form['field_moj_episode']['widget'][0]['value']['#states']['required'] = [
        ':input[name="field_moj_series"]' => $this->episodeSortingStates,
        'and',
        ':input[name="field_not_in_series[value]"]' => ['checked' => FALSE],
      ];
    }

    if (empty($this->releaseDateSortingStates)) {
      hide($form['group_release_date']);
    }
    else {
      $form['group_release_date']['#states']['visible'][':input[name="field_moj_series"]'] = $this->releaseDateSortingStates;
      // Currently we cannot set a required state for field_release_date.
      // See https://www.drupal.org/project/drupal/issues/2419131
      // However, this field has a default value (of the current date),
      // so it's less likely to not be set.
    }

    $form['group_category']['#states']['visible'][':input[name="field_not_in_series[value]"]']['checked'] = TRUE;
    $form['field_moj_top_level_categories']['widget']['#states']['required'][':input[name="field_not_in_series[value]"]']['checked'] = TRUE;
    $form['field_moj_series']['#states']['visible'][':input[name="field_not_in_series[value]"]']['checked'] = FALSE;
    $form['field_moj_series']['widget']['#states']['required'][':input[name="field_not_in_series[value]"]']['checked'] = FALSE;
    $form['group_season_and_episode_number']['#states']['visible'][':input[name="field_not_in_series[value]"]']['checked'] = FALSE;
    $form['group_release_date']['#states']['visible'][':input[name="field_not_in_series[value]"]']['checked'] = FALSE;
  }

}
