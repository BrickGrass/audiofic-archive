<?php

namespace Drupal\audiofic_archive_wrangling\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to dynamic route events to override the user cancel route.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {

    if ($route = $collection->get('entity.node.canonical')) {
      $route->setDefault('_controller', 'Drupal\audiofic_archive_wrangling\Controller\TagWranglingHelpPageController::view');
    }

  }

}
