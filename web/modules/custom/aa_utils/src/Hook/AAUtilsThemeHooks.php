<?php

namespace Drupal\aa_utils\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Class AAUtilsThemeHooks defines theme hooks for the aa_utils module.
 */
class AAUtilsThemeHooks {

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme($existing, $type, $theme, $path) {
    return [
      'node-contextual-menu' => [
        'template' => 'node-contextual-menu',
        'variables' => [
          'has_edit_access' => '',
          'can_remove_attribution' => '',
          'nid' => '',
          'uid' => '',
        ],
      ],

      'user-contextual-menu' => [
        'template' => 'user-contextual-menu',
        'variables' => [
          'has_edit_access' => '',
          'uid' => '',
        ],
      ],

      'content-count' => [
        'template' => 'content-count',
        'variables' => [
          'work_count' => 0,
          'fandom_count' => 0,
          'user_count' => 0,
        ],
      ],
    ];
  }

}
