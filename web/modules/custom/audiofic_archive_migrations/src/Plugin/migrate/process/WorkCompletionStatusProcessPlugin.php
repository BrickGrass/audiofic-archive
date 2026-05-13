<?php

namespace Drupal\audiofic_archive_migrations\Plugin\migrate\process;

use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * WorkCompletionStatusProcessPlugin sets the completion status.
 *
 * It checks the info field for the "info|wip" tag to determine this.
 */
#[MigrateProcess(
  id: "aa_completion_status",
  handle_multiples: TRUE,
)]
class WorkCompletionStatusProcessPlugin extends ProcessPluginBase {

  protected const WIP_TERM_ID = 206;

  /**
   * {@inheritdoc}
   */
  public function transform(
    $value,
    MigrateExecutableInterface $migrate_executable,
    Row $row,
    $destination_property,
  ) {
    $info_terms = $row->getSourceProperty($this->configuration['source']);
    foreach ($info_terms as $term) {
      if (in_array($this::WIP_TERM_ID, $term)) {
        return ['value' => 'wip'];
      }
    }

    return ['value' => 'complete'];
  }

}
