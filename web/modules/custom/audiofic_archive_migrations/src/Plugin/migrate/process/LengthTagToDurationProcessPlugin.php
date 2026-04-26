<?php

namespace Drupal\audiofic_archive_migrations\Plugin\migrate\process;

use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * LengthTagToDurationProcessPlugin converts a length range tag to a duration.
 *
 * This is used for sorting & filtering in the search view.
 */
#[MigrateProcess(
  id: "aa_length_tag_to_duration",
  handle_multiples: FALSE,
)]
class LengthTagToDurationProcessPlugin extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    // TODO: Lookup length tag label from id in value & d10 site's length tags
    // Use utils function to convert to duration in datetime+seconds
    // output for use in setting duration on work.

    return ['duration' => 0, 'seconds' => 0];
  }

}
