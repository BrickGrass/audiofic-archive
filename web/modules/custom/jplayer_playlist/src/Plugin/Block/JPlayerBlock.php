<?php

namespace Drupal\jplayer_playlist\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'JPlayer' Block.
 *
 * @Block(
 *   id = "jplayer_block",
 *   admin_label = @Translation("JPlayer block"),
 * )
 */
class JPlayerBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build['jplayer'] = [
      '#theme' => 'jplayer',
      '#attached' => [
        'library' => [
          'jplayer_playlist/jplayer',
        ],
      ],
    ];

    return $build;
  }

}