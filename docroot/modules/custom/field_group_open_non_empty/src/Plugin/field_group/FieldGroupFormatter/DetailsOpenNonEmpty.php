<?php

namespace Drupal\field_group_open_non_empty\Plugin\field_group\FieldGroupFormatter;

use Drupal\Core\Render\Element;
use Drupal\field_group\Plugin\field_group\FieldGroupFormatter\Details;

/**
 * Details element.
 *
 * @FieldGroupFormatter(
 *   id = "details_open_non_empty",
 *   label = @Translation("Details open when non-empty"),
 *   description = @Translation("Details element that is closed by default, and opened when there are non-empty fields inside it."),
 *   supported_contexts = {
 *     "form",
 *     "view"
 *   }
 * )
 */
class DetailsOpenNonEmpty extends Details {

  /**
   * {@inheritdoc}
   */
  public function settingsForm() {
    $form = parent::settingsForm();
    unset($form['open']);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function process(&$element, $processed_object) {
    parent::process($element, $processed_object);
    foreach (Element::children($processed_object) as $id) {
      if (isset($processed_object[$id]['#group']) && $processed_object[$id]['#group'] == $this->group->group_name) {
        if (!empty($processed_object[$id]['widget']['#default_value'])) {
          $element['#open'] = TRUE;
          return;
        }
      }
    }
  }


}
