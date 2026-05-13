<?php

namespace Drupal\audiofic_archive_migrations\EventSubscriber;

use Drupal\audiofic_archive_migrations\Plugin\migrate\process\FormatInfoMergeTermsProcessPlugin;
use Drupal\Core\Entity\EntityTypeManagerInterface;

use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate_plus\Event\MigrateEvents;
use Drupal\migrate_plus\Event\MigratePrepareRowEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Maps old format terms to new format terms, ensuring duplicates are merged.
 */
class FormatInfoMergeTermsEventSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected EntityTypeManagerInterface $entity_type_manager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      MigrateEvents::PREPARE_ROW => 'prepareRow',
    ];
  }

  /**
   * Map old format terms to new ones.
   *
   * If a new tag already exists skip the duplicate row, but save the id
   * for migration mapping!
   */
  public function prepareRow(MigratePrepareRowEvent $event) {
    /** @var \Drupal\migrate\Plugin\Migration $migration */
    $migration = $event->getMigration();
    $destination_definition = $migration->getDestinationConfiguration();

    if ($destination_definition['plugin'] != 'entity:taxonomy_term') {
      return;
    }

    /** @var \Drupal\migrate\Row $row */
    $row = $event->getRow();
    /** @var \Drupal\migrate\Plugin\migrate\id_map\Sql $id_map */
    $id_map = $migration->getIdMap();
    $process_plugins = $migration->getProcessPlugins();

    foreach ($process_plugins['name'] as $process_plugin) {
      if (!($process_plugin instanceof FormatInfoMergeTermsProcessPlugin)) {
        continue;
      }

      $source_field = $process_plugin->getSourceFieldName();
      $term_name = $row->getSource()[$source_field];
      if (!isset($process_plugin::FORMAT_MAPPING[$term_name])) {
        throw new MigrateSkipRowException('', FALSE);
      }

      $storage = $this->entity_type_manager->getStorage('taxonomy_term');
      $terms = $storage->loadByProperties([
        'name' => $process_plugin::FORMAT_MAPPING[$term_name],
        'vid' => 'info',
      ]);
      if (!empty($terms)) {
        /** @var \Drupal\taxonomy\Entity\Term $term */
        $term = reset($terms);
        $id_map->saveIdMapping($row, [$term->id()]);
        throw new MigrateSkipRowException('', FALSE);
      }
    }
  }

}
