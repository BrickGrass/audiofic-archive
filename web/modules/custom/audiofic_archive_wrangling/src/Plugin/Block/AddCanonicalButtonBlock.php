<?php

namespace Drupal\audiofic_archive_wrangling\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Url;

/**
 * Displays a "Create Canonical Tag" button.
 *
 * @Block(
 *   id = "add_canonical_button",
 *   admin_label = @Translation("Add Canonical Button Block"),
 *   category = @Translation("Menus"),
 * )
 */
class AddCanonicalButtonBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#theme' => 'add_canonical_button',
      '#route' => Url::fromRoute('audiofic_archive_wrangling.create_canonical'),
      '#cache' => [
        'contexts' => ['route'],
      ],
    ];
  }

}
