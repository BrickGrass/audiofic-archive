<?php

namespace Drupal\aa_gin\Routing;

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

    if ($route = $collection->get('entity.user.cancel_form')) {
      $route->setDefault('_title', 'Are you sure you want to delete your account?');
      $route->setDefault('_form', '\Drupal\aa_gin\Form\UserCancelForm');
    }

  }

}
