<?php

namespace Drupal\audiofic_archive_wrangling\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * AudioficArchiveWranglingThemeHooks defines theme hooks for this module.
 */
class AudioficArchiveWranglingThemeHooks {

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme($existing, $type, $theme, $path) {
    return [
      'browse_canon_fandoms' => [
        'template' => 'browse-canon-fandoms',
        'variables' => [
          'fandoms' => [],
          'top_level' => FALSE,
        ],
      ],
      'add_canonical_button' => [
        'template' => 'add-canonical-button',
        'variables' => [
          'route' => '',
        ],
      ],
    ];
  }

}
