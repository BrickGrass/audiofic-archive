<?php

namespace Drupal\series_part_label_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the NotEmptyWhenPartOfSeries constraint.
 */
class NotEmptyWhenPartOfSeriesValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->context->getRoot()->getValue();
    if ($entity->hasField("field_series") && $entity->get("field_series")[0] != NULL && $value->isEmpty()) {
      $this->context->addViolation($constraint->needsValue, [
        '%field' => $value->getFieldDefinition()->getLabel(),
      ]);
    }
  }

}
