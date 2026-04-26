<?php

namespace Drupal\propagate_metadata\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Ensures that canonicity aware tag cannot be made canonical & have a sibling.
 *
 * @Constraint(
 *   id = "CanonicalTagCannotHaveSibling",
 *   label = @Translation("A canonicity aware tag cannot be canonical and also have a canonical sibling.", context="Validation"),
 *   type = "string",
 * )
 */
class CanonicalTagCannotHaveSiblingConstraint extends Constraint {

  /**
   * The error message.
   *
   * @var string
   */
  public $errorMessage = 'The Tag %tag_name is canonical, it cannot have a canonical older sibling.';

}
