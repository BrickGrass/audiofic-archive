<?php

namespace Drupal\propagate_metadata\Hook;

use Drupal\aa_utils\Service\AudioficUtils;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\file\Entity\File;
use Drupal\media\MediaInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\Entity\User;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Class PropagateMetadataPresaveHooks.
 *
 * Ensures that entities have the correct data set when
 * saved. Manages the following entities:
 * - node.work
 * - node.playlist
 * - node.legacy_work
 * - media
 */
class PropagateMetadataPresaveHooks {
  use StringTranslationTrait;

  public function __construct(
    protected AudioficUtils $utils,
    protected EntityTypeManagerInterface $entity_type_manager,
    protected FileSystemInterface $file_system,
    protected AccountInterface $current_user,
    protected MessengerInterface $messenger,
  ) {}

  /**
   * Implements hook_ENTITY_TYPE_presave() for nodes.
   *
   * Ensures that edits to works/series always update series metadata
   * and that work durations are calculated correctly.
   */
  #[Hook('node_presave')]
  public function nodePresave(NodeInterface $node) {

    switch ($node->getType()) {
      case 'work':
        $duration = $this->setWorkDuration($node);
        // TODO: if a user removes themselves as an owner, will that data be correctly
        // propagated down to their related series?
        $this->enforceOwnerRules($node);
        $this->updateAllCollections($node, updated_duration_seconds: $duration);
        $this->setMediaOwner($node);
        break;

      case 'legacy_work':
        $this->setLegacyWorkDuration($node);
        $this->enforceOwnerRules($node);
        break;

      case 'playlist':
        // Only update series metadata if a work is added/removed
        // otherwise correct metadata will be overwritten.
        if (is_null($node->getOriginal())) {
          return;
        }

        $old_works = $node->getOriginal()->get('field_works_series')->referencedEntities();
        $new_works = $node->get('field_works_series')->referencedEntities();

        $old_work_ids = array_map(fn ($work) => $work->id(), $old_works);
        $new_work_ids = array_map(fn ($work) => $work->id(), $new_works);
        if ($this->arraysAreIdentical($old_work_ids, $new_work_ids)) {
          return;
        }

        $this->utils->setCollectionMetadata($node, $new_works, save: FALSE);
        break;
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_delete() for nodes.
   */
  #[Hook('node_delete')]
  public function nodeDelete(NodeInterface $node) {
    $this->deleteMedia($node);

    if ($node->getType() !== 'work') {
      return;
    }

    $this->updateAllCollections($node, work_deleted: TRUE);
  }

  /**
   * Implements hook_ENTITY_TYPE_presave() for media.
   *
   * Sets the field_duration_seconds field for any streaming media that
   * is created inside a work.
   */
  #[Hook('media_presave')]
  public function mediaPresave(MediaInterface $media) {
    if ($media->bundle() !== 'mp3_file') {
      return;
    }

    // No need to set duration again if it is already there.
    if (count($media->get('field_duration_seconds')) > 0) {
      return;
    }

    $file = File::load($media->get('field_media_audio_file')[0]->target_id);
    $file_path_on_disk = $this->file_system->realpath($file->getFileUri());

    $getID3 = new \getID3();
    $file_info = $getID3->analyze($file_path_on_disk);
    $duration = round($file_info['playtime_seconds']);

    $media->set('field_duration_seconds', $duration);
  }

  /**
   * Tests whether two arrays contain the same values, irregardless of order.
   */
  private function arraysAreIdentical(array $array_1, array $array_2): bool {
    return $array_1 === array_intersect($array_1, $array_2) &&
           $array_2 === array_intersect($array_2, $array_1);
  }

  /**
   * Sets the duration of a work, calculating it from the files attached.
   *
   * @param \Drupal\node\NodeInterface $work
   *   The work to calulate & set a new duration for.
   *
   * @return int
   *   The new duration in seconds.
   */
  private function setWorkDuration(NodeInterface $work): int {
    $duration = 0;

    foreach ($work->get("field_mp3_files")->referencedEntities() as $media) {
      $use_for_duration = $media->get('field_use_for_duration')[0] ?? NULL;
      if (empty($use_for_duration) || $use_for_duration->value === "0") {
        continue;
      }

      $media_duration = $media->get("field_duration_seconds")[0] ?? NULL;
      if (!empty($media_duration)) {
        $duration += $media_duration->value;
      }
    }

    $duration_interval = $this->utils->secondsToDateInterval($duration);
    $work->set('field_duration', ['duration' => $duration_interval, 'seconds' => $duration]);
    return $duration;
  }

  /**
   * Sets the duration of a legacy work, based on it's length tag.
   */
  private function setLegacyWorkDuration(NodeInterface $legacy_work) {
    $length = $legacy_work->get('field_length')->referencedEntities();
    if (empty($length)) {
      return;
    }

    $duration = $this->utils->convertLengthRangeToDuration($length[0]);
    if (empty($duration)) {
      return;
    }

    $duration_interval = $this->utils->secondsToDateInterval($duration);
    $legacy_work->set('field_duration', ['duration' => $duration_interval, 'seconds' => $duration]);
  }

  /**
   * Updates the metadata of all collections related to a work.
   */
  private function updateAllCollections(NodeInterface $work, int $updated_duration_seconds = 0, bool $work_deleted = FALSE) {
    foreach ($work->get('field_series')->referencedEntities() as $series) {
      $work_ids = $this->entity_type_manager->getStorage('node')->getQuery('AND')
        ->condition('type', 'work')
        ->condition('field_series.entity:node.nid', $series->id())
        ->condition('nid', [$work->id()], 'NOT IN')
        ->accessCheck(TRUE)
        ->execute();
      $works = Node::loadMultiple($work_ids);
      if (!$work_deleted) {
        $works[] = $work;
      }

      if (empty($works)) {
        $series->delete();
        continue;
      }

      $this->utils->setCollectionMetadata($series, $works, updated_work_id: $work->id(), updated_work_duration_seconds: $updated_duration_seconds);
    }
  }

  /**
   * Enforce rules on who can edit the owners of a node.
   */
  private function enforceOwnerRules(NodeInterface $node) {
    $user = User::load($this->current_user->id());
    if ($user && $user->hasRole('administrator')) {
      return;
    }

    if (empty($original = $node->getOriginal())) {
      // No previous node to check against.
      return;
    }

    $original_owners = array_column($original->get('field_owner')->getValue(), 'target_id');
    $updated_owners = array_column($node->get('field_owner')->getValue(), 'target_id');
    $removed_owners = array_diff($original_owners, $updated_owners);
    if (empty($removed_owners)) {
      return;
    }

    $user_tags = array_column($user->get('field_reader_name')->getValue(), 'target_id');
    $unauthorised_to_remove = array_diff($removed_owners, $user_tags);
    if (!empty($unauthorised_to_remove)) {
      $node->set('field_owner', array_merge(
        Term::loadMultiple($updated_owners),
        Term::loadMultiple($unauthorised_to_remove)
      ));

      $this->messenger->addError($this->t('You cannot remove other owners!'));
    }
  }

  /**
   * Fetches all media entities attached to a node (if any).
   */
  private function getAllMediaFromNode(NodeInterface $node): array {
    $streaming_files = [];
    $other_files = [];

    if ($node->hasField('field_mp3_files')) {
      $streaming_files = $node->get('field_mp3_files')->referencedEntities();
    }

    if ($node->hasField('field_other_files')) {
      $other_files = $node->get('field_mp3_files')->referencedEntities();
    }

    return array_merge($streaming_files, $other_files);
  }

  /**
   * Updates all media entities attached to a node with the new owner(s).
   */
  private function setMediaOwner(NodeInterface $node) {
    $all_media = $this->getAllMediaFromNode($node);
    $owners = $node->get('field_owner')->referencedEntities();

    /** @var \Drupal\media\MediaInterface $media */
    foreach ($all_media as $media) {
      $media->set('field_owner', $owners);
      $media->save();
    }
  }

  /**
   * Deletes all referenced media from a node.
   */
  private function deleteMedia(NodeInterface $node) {
    $all_media = $this->getAllMediaFromNode($node);

    /** @var \Drupal\media\MediaInterface $media */
    foreach ($all_media as $media) {
      $media->delete();
    }
  }

}
