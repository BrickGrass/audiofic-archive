<?php

namespace Drupal\audiofic_archive_migrations\Plugin\migrate\process;

use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * NewlinesToHtmlProcessPlugin converts newlines to html.
 *
 * Any linebreaks are converted to a </br> tag.
 */
#[MigrateProcess(
  id: "aa_newlines_to_html",
  handle_multiples: FALSE,
)]
class NewlinesToHtmlProcessPlugin extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    return '<p>' . str_replace("\n", "</br>", $value) . '</p>';
  }

}
