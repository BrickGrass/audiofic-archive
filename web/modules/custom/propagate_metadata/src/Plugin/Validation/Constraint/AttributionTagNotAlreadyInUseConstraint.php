<?php

namespace Drupal\propagate_metadata\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Ensures that a tag (reader/author/cover_artist) is not already in use.
 *
 * @Constraint(
 *   id = "AttributionTagNotAlreadyInUse",
 *   label = @Translation("User is not claiming an attribution tag which is already in use by another user.", context="Validation"),
 *   type = "string"
 * )
 */
class AttributionTagNotAlreadyInUseConstraint extends Constraint {

  /**
   * The error message.
   *
   * @var string
   */
  public $errorMessage = 'The User %user_displayname is already using the %tag_type tag %tag_name';

}
