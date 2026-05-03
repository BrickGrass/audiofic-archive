<?php

namespace Drupal\audiofic_archive_migrations\Plugin\migrate\process;

use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * FandomCanonicityProcessPlugin correctly sets the canonicity of fandoms.
 *
 * All fandoms default to being "canon", but any which are in the root fandom
 * list are made "canonical_root_fandom".
 */
#[MigrateProcess(
  id: "aa_fandom_canonicity",
  handle_multiples: TRUE,
)]
class FandomCanonicityProcessPlugin extends ProcessPluginBase {

  /**
   * The list of root fandoms.
   *
   * @var array
   */
  public const array ROOT_FANDOMS = [
    'anime/manga/manhwa/donghua fandoms',
    'audio series',
    'board, card, & tabletop games',
    'comics fandoms',
    'gaming fandoms',
    'literature fandoms',
    'meta fandoms',
    'movie fandoms - animation',
    'movie fandoms - live action',
    'other media',
    'rpf fandoms',
    'short film, ads, and videos',
    'television fandoms - animation',
    'television fandoms - live action',
    'theatre/stage fandoms',
    'web series',
    'no fandom specified',
  ];

  /**
   * {@inheritdoc}
   */
  public function transform(
    $value,
    MigrateExecutableInterface $migrate_executable,
    Row $row,
    $destination_property,
  ) {
    $name = $row->getSourceProperty($this->configuration['source']);
    if (in_array($name, $this::ROOT_FANDOMS)) {
      return [['value' => 'canonical_root_fandom']];
    }

    return [['value' => 'canon']];
  }

}
