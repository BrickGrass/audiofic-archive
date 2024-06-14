<?php

namespace Drupal\audiofic_player\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'APlayer' Block.
 *
 * @Block(
 *   id = "aplayer_block",
 *   admin_label = @Translation("APlayer block"),
 * )
 */
class APlayerBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build['aplayer'] = [
      '#theme' => 'jplayer',
      '#attached' => [
        'library' => [
          'audiofic_player/jplayer',
        ],
      ],
    ];

    return $build;
  }

}