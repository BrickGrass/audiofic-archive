<?php

namespace Drupal\aa_gin\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;

class AAGinHooks {

  public function __construct(
    protected RouteMatchInterface $route_match,
  ) {}

  /**
   * Implements hook_page_attachments().
   *
   * Attatches frontend libraries to pages for the aa_gin module.
   */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$attachments) {
    $attachments['#attached']['library'][] = 'aa_gin/disable-title-nowrap';

    // Attach library that minimises gin form's field size &
    // includes js logic for readmore buttons.
    $route = $this->route_match->getRouteName();
    if (in_array($route, [
      'node.add', 'node.add_page', 'entity.node.edit_form',
      'entity.node.delete_form', 'entity.user.canonical',
    ])) {
      $attachments['#attached']['library'][] = 'aa_gin/content-form';
    }

  }

}
