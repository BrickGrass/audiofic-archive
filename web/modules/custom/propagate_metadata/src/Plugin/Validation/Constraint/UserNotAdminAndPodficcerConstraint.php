<?php

namespace Drupal\propagate_metadata\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that an administrator has not been given the Podficcer role.
 *
 * @Constraint(
 *   id = "UserNotAdminAndPodficcer",
 *   label = @Translation("User not both Administrator and Podficcer", context="Validation"),
 *   type = "string"
 * )
 */
class UserNotAdminAndPodficcerConstraint extends Constraint {

  /**
   * The message shown if an administrator is assigned the Podficcer role.
   *
   * @var string
   */
  public $errorMessage = 'Administrators cannot have the Podficcer role';

}
