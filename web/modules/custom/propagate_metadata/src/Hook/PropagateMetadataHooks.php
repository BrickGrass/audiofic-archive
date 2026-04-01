<?php

namespace Drupal\propagate_metadata\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Class PropagateMetadataHooks.
 *
 * Ensures that users are not saved with invalid data/settings.
 */
class PropagateMetadataHooks {

  /**
   * Implements hook_entity_type_alter().
   *
   * Adds validation constraints to the User entity.
   */
  #[Hook('entity_type_alter')]
  public function entityTypeAlter(&$entity_types) {
    if (isset($entity_types['user'])) {
      $entity_types['user']->addConstraint('UserNotAdminAndPodficcer');
      $entity_types['user']->addConstraint('AttributionTagNotAlreadyInUse');
    }
  }

}
