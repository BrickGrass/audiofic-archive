<?php

namespace Drupal\propagate_metadata\Plugin\Validation\Constraint;

use Drupal\user\Entity\Role;
use Drupal\user\UserInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the UserNotAdminAndPodficcer constraint.
 */
class UserNotAdminAndPodficcerConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    if (!$value instanceof UserInterface) {
      return;
    }

    $is_admin = FALSE;
    $is_podficcer = FALSE;

    foreach (Role::loadMultiple($value->getRoles()) as $role) {
      if ($role->isAdmin()) {
        $is_admin = TRUE;
      }

      if ($role->label() === "Podficcer") {
        $is_podficcer = TRUE;
      }
    }

    if ($is_admin && $is_podficcer) {
      $this->context->addViolation($constraint->errorMessage);
    }
  }

}
