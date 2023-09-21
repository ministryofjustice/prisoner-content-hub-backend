<?php

namespace Drupal\prisoner_hub_taxonomy_sorting;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;

/**
 * Service for manipulating sorting information on save.
 *
 * @package Drupal\prisoner_hub_taxonomy_sorting
 */
class EntityPreSave {
  use StringTranslationTrait;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Messenger service.
   */
  public function __construct(protected MessengerInterface $messenger) {
  }

  /**
   * Update 'series_sort_value' on nodes.
   *
   * Copy sorting value from either season+episode OR release date fields to the
   * series_sort_value field.
   *
   * @param \Drupal\node\NodeInterface $entity
   *   Entity being saved.
   */
  public function updatesSeriesSortValue(NodeInterface $entity) {
    if (!$entity->hasField('field_moj_series')) {
      return;
    }
    $series_result = $entity->field_moj_series->referencedEntities();
    if (empty($series_result)) {
      // No series attached to content, do nothing.
      return;
    }
    $series_entity = current($series_result);
    $sort_by_value = $series_entity->field_sort_by->getString();
    switch ($sort_by_value) {
      case 'season_and_episode_asc':
      case 'season_and_episode_desc':
        $season_number = $entity->field_moj_season->value;
        $episode_number = $entity->field_moj_episode->value;

        if ($season_number >= 1 && $season_number <= 999 && $episode_number >= 1 && $episode_number <= 999) {
          // Episode number must be padded to account for the different lengths
          // upto 999.  E.g. season 1 episode 15 could be mixed up
          // with season 11 episode 5.
          $episode_number_padded = str_pad($episode_number, 3, '0', STR_PAD_LEFT);
          $calculated_sort_value = (int) $season_number . $episode_number_padded;
        }
        else {
          // If either season or episode number are out of range, set the value
          // to 0 and warn the user.  This should only ever happen if content is
          // being bulk updated. (As when editing the content directly, the
          // fields are made mandatory and don't allow numbers outside the
          // range).
          $this->messenger->addWarning($this->t('Missing season or episode number for :content. This could effect how the content is sorted within a series.', [':content' => $entity->label()]));
          $calculated_sort_value = 0;
        }
        break;

      case 'release_date_asc':
      case 'release_date_desc':
        if ($entity->field_release_date->date) {
          $calculated_sort_value = $entity->field_release_date->date->getTimestamp();
        }
        else {
          // If release date field is empty, set the value to 0 and
          // warn the user.  This should only ever happen if content is being
          // bulk updated. (As when editing the content directly, the field is
          // made mandatory).
          $this->messenger->addWarning($this->t('Missing release date for :content. This could effect how the content is sorted within a series.', [':content' => $entity->label()]));
          $calculated_sort_value = 0;
        }
        break;
    }

    // If the sorting is descending, cast this as a negative number to invert
    // the direction.  This allows us to always use ASC direction when running
    // queries, e.g. through JSON:API.
    if (substr($sort_by_value, -4) == 'desc') {
      $calculated_sort_value = -$calculated_sort_value;
    }
    $entity->set('series_sort_value', $calculated_sort_value);
  }

}
