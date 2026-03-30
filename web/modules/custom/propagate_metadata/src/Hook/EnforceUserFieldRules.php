<?php

namespace Drupal\propagate_metadata\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\UserInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\Entity\User;

/**
 * Class EnforceUserFieldRules.
 *
 * Ensures that users are not saved with invalid data/settings.
 */
class EnforceUserFieldRules {
  use StringTranslationTrait;

  public function __construct(
    protected readonly MessengerInterface $messenger,
    protected readonly EntityTypeManagerInterface $entity_type_manager,
  ) {}

  /**
   * Implements hook_ENTITY_TYPE_presave() for users.
   *
   * Enforces rules when saving users, such as:
   * - Administrators cannot have the podficcer role - this
   *   breaks series links when an admin edits another persons
   *   work, as it causes restrictions that shouldn't apply to
   *   admins to apply!
   * - A reader, author or cover artist tag cannot be linked to more
   *   than one user.
   */
  #[Hook('user_presave')]
  public function userPresave(UserInterface $user) {
    $this->ensureNotPodficcerAndAdministrator($user);

    $this->ensureTagsAreNotInUse($user, 'field_reader_name');
    $this->ensureTagsAreNotInUse($user, 'field_author_name');
    $this->ensureTagsAreNotInUse($user, 'field_cover_artist_name');
  }

  /**
   * Ensures that a user is not both an admin & has the Podficcer role.
   */
  private function ensureNotPodficcerAndAdministrator(UserInterface $user) {
    $is_admin = FALSE;
    $is_podficcer = FALSE;
    $podfic_role_id = "";

    /** @var \Drupal\user\Entity\Role $role */
    foreach ($user->get('roles')->referencedEntities() as $role) {
      if ($role->isAdmin()) {
        $is_admin = TRUE;
      }

      if ($role->label() === "Podficcer") {
        $is_podficcer = TRUE;
        $podfic_role_id = $role->id();
      }
    }

    if ($is_admin && $is_podficcer) {
      $user->removeRole($podfic_role_id);
      $this->messenger->addWarning($this->t('Administrators cannot have the Podficcer role, the role has been removed.'));
    }
  }

  /**
   * Checks if a any of the tags in a field are already in use on another user.
   *
   * If they are, the tag is removed from this user and a message is sent
   * informing the form editor of this.
   */
  private function ensureTagsAreNotInUse(UserInterface $user, string $field_name) {
    $current_tags = $user->get($field_name)->referencedEntities();
    $newly_added_tags = $this->getNewlyAddedTags($user, $field_name);

    $tags_to_remove = $this->getTagsToRemove($user, $field_name, $newly_added_tags);

    $remaining_tags = [];
    foreach ($current_tags as $current_tag) {
      if (!in_array($current_tag->id(), $tags_to_remove)) {
        $remaining_tags[] = $current_tag;
      }
    }
    $user->set($field_name, $remaining_tags);
  }

  /**
   * Gets all tags that were added to the specifed field.
   */
  private function getNewlyAddedTags(UserInterface $user, string $field_name): array {
    $current_tags = $user->get($field_name)->referencedEntities();
    $original_user = $user->getOriginal();

    $newly_added_tags = [];
    if ($original_user) {
      $original_tag_ids = array_map(
        fn ($t) => $t->id(),
        $original_user->get($field_name)->referencedEntities()
      );
      foreach ($current_tags as $tag) {
        if (!in_array($tag->id(), $original_tag_ids)) {
          $newly_added_tags[] = $tag;
        }
      }
      return $newly_added_tags;
    }

    return $current_tags;
  }

  /**
   * Finds a list of all tag ids to remove from this user.
   *
   * Whenever a tag is marked on the to-remove list, a message is sent
   * informing the form-editor of this & why.
   */
  private function getTagsToRemove(UserInterface $user, string $field_name, $newly_added_tags): array {
    if (!$newly_added_tags) {
      return [];
    }
    $vocabulary_label = Vocabulary::load(array_first($newly_added_tags)->bundle())->label();

    $tags_to_remove = [];
    foreach ($newly_added_tags as $tag) {
      /** @var \Drupal\user\UserInterface[] $users_with_tag */
      $users_with_tag = $this->entity_type_manager->getStorage('user')->getQuery()
        ->condition("$field_name.entity:taxonomy_term.tid", [$tag->id()], 'IN')
        ->condition('uid', [$user->id()], 'NOT IN')
        ->accessCheck(TRUE)
        ->execute();
      if (!$users_with_tag) {
        continue;
      }

      $tags_to_remove[] = $tag->id();
      $this->messenger->addWarning($this->t(
        'The User @user_displayname is already using the @tag_type tag @tag_name, removing it from this user.',
        [
          '@user_displayname' => User::load(array_first($users_with_tag))->getDisplayName(),
          '@tag_type' => $vocabulary_label,
          '@tag_name' => $tag->getName(),
        ]
      ));
    }
    return $tags_to_remove;
  }

}
