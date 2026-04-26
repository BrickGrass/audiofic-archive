<?php

namespace Drupal\propagate_metadata\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Ensures that non-canonical tags have siblings assigned.
 *
 * @Constraint(
 *   id = "NonCanonicalTagMustHaveSibling",
 *   label = @Translation("Ensures that non-canonical tags have siblings assigned.", context="Validation"),
 *   type = "string"
 * )
 */
class NonCanonicalTagMustHaveSiblingConstraint extends Constraint {

  /**
   * No sibling error.
   *
   * @var string
   */
  public $missingErrorMessage = 'For the tag %tag_name to be made non-canonical, it must have a canonical sibling assigned';

  /**
   * Cannot fetch sibling error.
   *
   * @var string
   */
  public $siblingDoesntExistErrorMessage = 'The canonical sibling tag with id %sibling_id could not be found';

  /**
   * Sibling tag not canonical error.
   *
   * @var string
   */
  public $siblingNotCanonicalErrorMessage = 'You cannot assign a non-canonical tag (%sibling) as the canonical sibling of %target';

}
