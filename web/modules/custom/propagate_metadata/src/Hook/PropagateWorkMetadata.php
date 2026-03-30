<?php

namespace Drupal\propagate_metadata\Hook;

use Drupal\aa_utils\Service\AudioficUtils;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\file\Entity\File;
use Drupal\media\MediaInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Class PropagateWorkMetadata.
 *
 * Ensures that the work/legacy_work/playlist node types
 * have the correct data set when saved.
 */
class PropagateWorkMetadata {

  public function __construct(
    protected readonly AudioficUtils $utils,
    protected readonly EntityTypeManagerInterface $entity_type_manager,
    protected readonly FileSystemInterface $file_system,
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
        $this->updateAllCollections($node, updated_duration_seconds: $duration);
        break;

      case 'legacy_work':
        $this->updateAllCollections($node);
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
    if (in_array($node->getType(), ['work', 'legacy_work'])) {
      $this->updateAllCollections($node, work_deleted: TRUE);
    }
  }

  /**
   * Tests whether two arrays contain the same values, irregardless of order.
   */
  private function arraysAreIdentical(array $array_1, array $array_2): bool {
    return $array_1 === array_intersect($array_1, $array_2) &&
           $array_2 === array_intersect($array_2, $array_1);
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

      switch ($work->getType()) {
        case 'work':
          $this->utils->setCollectionMetadata($series, $works, updated_work_id: $work->id(), updated_work_duration_seconds: $updated_duration_seconds);
          break;

        case 'legacy_work':
          $this->utils->setCollectionMetadata($series, $works);
          break;
      }
    }
  }

}
