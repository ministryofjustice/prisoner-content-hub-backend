<?php

namespace Drupal\prisoner_hub_taxonomy_field_ux\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Constraint for ensuring content is in a category or series.
 *
 * @Constraint(
 *   id = "CategoryOrSeries",
 *   label = @Translation("Category or Series", context = "Validation"),
 * )
 */
class CategoryOrSeries extends Constraint {
  /**
   * The message that will be shown if the constraint fails.
   */
  public string $notInCategoryOrSeries = 'This content must be placed in a category or series.';

}
