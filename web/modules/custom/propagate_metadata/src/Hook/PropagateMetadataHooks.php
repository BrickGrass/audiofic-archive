<?php

namespace Drupal\propagate_metadata\Hook;

use Drupal\aa_utils\Service\AudioficUtils;
use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\User;

// View permission realm & options.
define('AUDIOFIC_REALM_VIEW', 'audiofic_realm_view');
define('AUDIOFIC_GRANT_PUBLIC', 0);
define('AUDIOFIC_GRANT_ARCHIVE_LOCKED', 1);
define('AUDIOFIC_GRANT_ARCHIVE_UNPUBLISHED', 2);

// Edit/Update/Delete permission realm & admin grant,
// all other grants are based on owner roles.
define('AUDIOFIC_REALM_EDIT', 'audiofic_realm_edit');
define('AUDIOFIC_GRANT_ADMIN', 0);

/**
 * Class PropagateMetadataHooks.
 *
 * Ensures that users are not saved with invalid data/settings.
 */
class PropagateMetadataHooks {

  public function __construct(
    protected AudioficUtils $utils,
  ) {}

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

    if (isset($entity_types['taxonomy_term'])) {
      $entity_types['taxonomy_term']->addConstraint('CanonicalTagCannotHaveSibling');
      $entity_types['taxonomy_term']->addConstraint('NonCanonicalTagMustHaveSibling');
    }
  }

  /**
   * Implements hook_node_grants().
   *
   * Defines the access permissions a user has, depending on the operation
   * that is being checked (eg: view, update, delete, etc).
   */
  #[Hook('node_grants')]
  public function nodeGrants(AccountInterface $account, string $operation): array {
    $grants = [];
    $roles = $account->getRoles();

    if ($operation == 'view') {
      if (in_array('administrator', $roles)) {
        $grants[AUDIOFIC_REALM_VIEW] = [
          AUDIOFIC_GRANT_PUBLIC,
          AUDIOFIC_GRANT_ARCHIVE_LOCKED,
          AUDIOFIC_GRANT_ARCHIVE_UNPUBLISHED,
        ];
      } elseif (in_array('authenticated', $roles)) {
        $grants[AUDIOFIC_REALM_VIEW] = [
          AUDIOFIC_GRANT_PUBLIC,
          AUDIOFIC_GRANT_ARCHIVE_LOCKED,
        ];
      } else {
        $grants[AUDIOFIC_REALM_VIEW] = [
          AUDIOFIC_GRANT_PUBLIC,
        ];
      }
    } else {
      if (in_array('administrator', $roles)) {
        $grants[AUDIOFIC_REALM_EDIT] = [
          AUDIOFIC_GRANT_ADMIN,
        ];
      } elseif (in_array('authenticated', $roles)) {
        $user = User::load($account->id());
        if (!empty($user)) {
          $user_tags = array_column($user->get('field_reader_name')->getValue(), 'target_id');
          $grants[AUDIOFIC_REALM_EDIT] = $user_tags;
        }
      }
    }

    return $grants;
  }

  /**
   * Implements hook_node_access_records().
   *
   * Defines the access grants that a user needs to access this node.
   */
  #[Hook('node_access_records')]
  public function nodeAccess(NodeInterface $node): array {
    $grants = [];

    // Unpublished nodes can only be viewed by admins.
    // Archive locked nodes can only be viewed by logged in users.
    // Published & non-locked nodes can be viewed by anyone.
    if (!$node->isPublished()) {
      $grants[] = [
        'realm' => AUDIOFIC_REALM_VIEW,
        'gid' => AUDIOFIC_GRANT_ARCHIVE_UNPUBLISHED,
        'grant_view' => 1,
        'grant_update' => 0,
        'grant_delete' => 0,
        'priority' => 0,
      ];
    } elseif ($this->utils->isNodeArchiveLocked($node)) {
      $grants[] = [
        'realm' => AUDIOFIC_REALM_VIEW,
        'gid' => AUDIOFIC_GRANT_ARCHIVE_LOCKED,
        'grant_view' => 1,
        'grant_update' => 0,
        'grant_delete' => 0,
        'priority' => 0,
      ];
    } else {
      $grants[] = [
        'realm' => AUDIOFIC_REALM_VIEW,
        'gid' => AUDIOFIC_GRANT_PUBLIC,
        'grant_view' => 1,
        'grant_update' => 0,
        'grant_delete' => 0,
        'priority' => 0,
      ];
    }

    // Admins have edit access to any/all nodes.
    $grants[] = [
      'realm' => AUDIOFIC_REALM_EDIT,
      'gid' => AUDIOFIC_GRANT_ADMIN,
      'grant_view' => 1,
      'grant_update' => 1,
      'grant_delete' => 1,
      'priority' => 0,
    ];

    // Non-admin users only have edit access to nodes they own.
    if ($node->hasField('field_owner')) {
      $owners = array_column($node->get('field_owner')->getValue(), 'target_id');
      foreach ($owners as $owner_id) {
        $grants[] = [
          'realm' => AUDIOFIC_REALM_EDIT,
          'gid' => $owner_id,
          'grant_view' => 1,
          'grant_update' => 1,
          'grant_delete' => 1,
          'priority' => 0,
        ];
      }
    }

    return $grants;
  }

  /**
   * Implements hook_ENTITY_TYPE_access() for media.
   *
   * Used to define custom access based on the user's reader tag.
   */
  #[Hook('media_access')]
  public function mediaAccess(MediaInterface $media, $operation, AccountInterface $account) {
    $roles = $account->getRoles();

    if (in_array('administrator', $roles)) {
      return AccessResultAllowed::allowed();
    } elseif (!in_array('content_editor', $roles)) {
      return AccessResultForbidden::forbidden();
    }

    $user = User::load($account->id());
    if (empty($user)) {
      return AccessResultForbidden::forbidden();
    }

    $user_tags = array_column($user->get('field_reader_name')->getValue(), 'target_id');
    $media_owners = array_column($media->get('field_owner')->getValue(), 'target_id');
    foreach ($user_tags as $user_tag) {
      if (in_array($user_tag, $media_owners)) {
        return AccessResultAllowed::allowed();
      }
    }
    return AccessResultForbidden::forbidden();
  }

}
