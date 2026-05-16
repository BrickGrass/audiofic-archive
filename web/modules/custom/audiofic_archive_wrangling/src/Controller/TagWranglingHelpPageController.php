<?php

namespace Drupal\audiofic_archive_wrangling\Controller;

use Drupal\Core\Entity\EntityInterface;
use Drupal\node\Controller\NodeViewController;

/**
 * A controller to allow overriding route options for tag wrangling help pages.
 */
class TagWranglingHelpPageController extends NodeViewController {

  /**
   * {@inheritdoc}
   */
  public function view(EntityInterface $node, $view_mode = 'full', $langcode = NULL) {
    if ($node->bundle() === 'aa_tag_wrangling_help_page') {
      $request = \Drupal::request();
      /** @var \Symfony\Component\Routing\Route $route */
      if ($route = $request->attributes->get('_route_object')) {
        $route->setOption('_admin_route', TRUE);
      }
    }

    return parent::view($node, $view_mode, $langcode);
  }

}
