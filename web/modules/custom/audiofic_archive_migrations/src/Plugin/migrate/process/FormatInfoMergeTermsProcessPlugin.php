<?php

namespace Drupal\audiofic_archive_migrations\Plugin\migrate\process;

use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * FormatInfoMergeProcessPlugin maps the old format tags to our new ones.
 *
 * This process does nothing but the definition of the plugin will trigger an
 * event.
 *
 * @see Drupal\audiofic_archive_migrations\EventSubscriber\FormatInfoMergeTermsEventSubscriber
 */
#[MigrateProcess(
  id: "aa_format_merge_terms",
  handle_multiples: FALSE,
)]
class FormatInfoMergeTermsProcessPlugin extends ProcessPluginBase {

  public const FORMAT_MAPPING = [
    'info|anthology' => 'compilation',
    'anthology|multiple reader' => 'compilation',
    'anthology|single reader' => 'compilation',
    'info|collaboration' => 'other collaboration',
    'collaboration|podtogether' => 'other collaboration',
    'collaboration|ensemble' => 'multivoice',
    'collaboration|two voices' => 'multivoice',
    'info|improvisational podfic' => 'improvisation',
    'info|non-podfic format' => 'SORT MANUALLY - info|non-podfic format',
    'format|filk' => 'filk',
    'info|poetry' => 'poetry',
    'format|podvid' => 'vid',
    'info|includes freetalk' => 'commentary',
    'info|meta' => 'meta',
  ];

  /**
   * Return source field name.
   */
  public function getSourceFieldName(): string {
    return $this->configuration['source'];
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $term_name = $row->getSourceProperty($this->getSourceFieldName());
    if (isset($this::FORMAT_MAPPING[$term_name])) {
      return ['value' => $this::FORMAT_MAPPING[$term_name]];
    }
    throw new MigrateSkipRowException('', FALSE);
  }

}
