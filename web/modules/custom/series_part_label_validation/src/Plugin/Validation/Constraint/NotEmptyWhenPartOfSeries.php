<?php

namespace Drupal\series_part_label_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Requires a field to have a value when the entity is linked to a series.
 *
 * @Constraint(
 *   id = "NotEmptyWhenPartOfSeries",
 *   label = @Translation("Not empty when part of a series", context = "Validation"),
 *   type = "string"
 * )
 */
class NotEmptyWhenPartOfSeries extends Constraint {

  public $needsValue = "%field field cannot be empty if this work is part of a series.";

}