<?php

namespace Drupal\aa_utils\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Class AAUtilsHooks defines hooks for the aa_utils module.
 */
class AAUtilsHooks {

  /**
   * Implements hook_cron().
   *
   * Defines a cron job which cleans up orphaned media/files.
   */
  #[Hook('cron')]
  public function cron() {
    /** @var \Drupal\media\MediaStorage $media_storage */
    $media_storage = \Drupal::entityTypeManager()->getStorage('media');

    $media_ids = $media_storage->getQuery()
      ->accessCheck(FALSE)
      ->execute();
    $media = $media_storage->loadMultiple($media_ids);
  }

}
