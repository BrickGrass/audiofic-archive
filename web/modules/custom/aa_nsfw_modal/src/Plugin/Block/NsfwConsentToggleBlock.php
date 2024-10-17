<?php

namespace Drupal\aa_nsfw_modal\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * A block with a switch that toggles between showing and hiding nsfw content.
 */
#[Block(
  id: "aa_nsfw_toggle_block",
  admin_label: new TranslatableMarkup("NSFW Content Toggle Switch Block"),
  category: new TranslatableMarkup("Blocks"),
)]

class NsfwConsentToggleBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#theme' => 'aa_nsfw_toggle_block',
      '#attached' => [
        'library' => [
          'aa_nsfw_modal/modal',
        ],
      ],
    ];
  }

}
