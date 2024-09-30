<?php

namespace Drupal\prisoner_hub_taxonomy_field_ux\Plugin\Validation\Constraint;

use Drupal\Core\Entity\FieldableEntityInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validator for ensuring content has a category or series.
 */
class CategoryOrSeriesValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $value->getEntity();
    if (!$entity) {
      return;
    }
    if (!$entity instanceof FieldableEntityInterface) {
      return;
    }
    if (!$entity->hasField('field_moj_series')) {
      return;
    }
    if (!$entity->hasField('field_moj_top_level_categories')) {
      return;
    }
    if ($entity->get('field_moj_series')->isEmpty()
      && $entity->get('field_moj_top_level_categories')->isEmpty()) {
      $this->context->buildViolation($constraint->notInCategoryOrSeries)
        ->atPath('field_moj_series')
        ->addViolation();
    }
  }

}
