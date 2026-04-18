<?php

namespace Drupal\aa_utils\Service;

use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\user\UserInterface;

/**
 * Class AudioficUtils.
 *
 * Provides utility functions to the audiofic archive codebase.
 */
class AudioficUtils {

  private const MINUTE = 60;
  private const HOUR = self::MINUTE * 60;
  private const LENGTHRANGEMAPPING = [
    'under 10 mins' =>                   self::MINUTE * 5,
    '10-20 mins'    =>                   self::MINUTE * 15,
    '20-30 mins'    =>                   self::MINUTE * 25,
    '30-45 mins'    =>                   self::MINUTE * 37,
    '45 mins-1hr'   =>                   self::MINUTE * 52,
    '1-1:30 hrs'   => self::HOUR      + self::MINUTE * 15,
    '1:30-2 hrs'   => self::HOUR      + self::MINUTE * 45,
    '2-2:30 hrs'   => self::HOUR * 2  + self::MINUTE * 15,
    '2:30-3 hrs'   => self::HOUR * 2  + self::MINUTE * 45,
    '3-3:30 hrs'   => self::HOUR * 3  + self::MINUTE * 15,
    '3:30-4 hrs'   => self::HOUR * 2  + self::MINUTE * 45,
    '4-4:30 hrs'   => self::HOUR * 4  + self::MINUTE * 15,
    '4:30-5 hrs'   => self::HOUR * 4  + self::MINUTE * 45,
    '5-7:30 hrs'   => self::HOUR * 6  + self::MINUTE * 15,
    '7:30-10 hrs'  => self::HOUR * 8  + self::MINUTE * 45,
    '10-15 hrs'    => self::HOUR * 12 + self::MINUTE * 30,
    '15-20 hrs'    => self::HOUR * 17 + self::MINUTE * 30,
    'over 20 hrs'  => self::HOUR * 20,
  ];

  /**
   * Determines whether an entity is rated nsfw (no rating or null is nsfw).
   */
  public function isNsfw(NodeInterface $node): bool {
    if (!$node->hasField('field_rating')) {
      return TRUE;
    }

    if ($rating = array_first($node->get('field_rating')->referencedEntities())) {
      if (in_array($rating->name->value, ['General', "Teen and up"])) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Iterates an array of nodes and counts the total with a nsfw rating.
   */
  public function getTotalNsfw(array $works): int {
    $total_nsfw = 0;

    foreach ($works as $work) {
      if ($this->isNsfw($work)) {
        $total_nsfw++;
      }
    }

    return $total_nsfw;
  }

  /**
   * Removes duplicate entities in an array.
   *
   * Uses the id() method to calculate uniqueness.
   */
  public function removeDuplicateEntities(array $entities): array {
    $ids = array_map(fn ($tag) => $tag->id(), $entities);
    $unique_ids = array_unique($ids);
    return array_values(array_intersect_key($entities, $unique_ids));
  }

  /**
   * Converts a length range tag to a duration in seconds.
   *
   * Returns NULL if the term provided has an unrecognised name.
   */
  public function convertLengthRangeToDuration(TermInterface $length): ?int {
    if (isset(self::LENGTHRANGEMAPPING[$length->getName()])) {
      return self::LENGTHRANGEMAPPING[$length->getName()];
    }

    return NULL;
  }

  /**
   * Converts a total N.O. seconds to a date interval.
   */
  public function secondsToDateInterval(int $total_seconds): \DateInterval {
    $a = new \DateTime('@0');
    $b = new \DateTime("@{$total_seconds}");
    return $a->diff($b);
  }

  /**
   * Checks whether a user is listed as a reader on a node.
   */
  public function isUserAttributed(?UserInterface $user, NodeInterface $node): bool {
    if (!$user) {
      return FALSE;
    }

    $user_reader_tags = array_column($user->get('field_reader_name')->getValue(), 'target_id');
    $node_reader_tags = array_column($node->get('field_reader')->getValue(), 'target_id');

    foreach ($user_reader_tags as $user_reader_tag) {
      if (in_array($user_reader_tag, $node_reader_tags)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Sets the metadata of a series/collection.
   */
  public function setCollectionMetadata(
    NodeInterface $collection,
    array $works,
    ?int $updated_work_id = 999999,
    int $updated_work_duration_seconds = 0,
    bool $save = TRUE,
  ) {
    $data = $this->fetchWorkData($works, $updated_work_id, $updated_work_duration_seconds);

    $collection->set('field_owner', $data['owners']);
    $collection->set('field_author', $data['authors']);
    $collection->set('field_reader', $data['readers']);
    $collection->set('field_fandom2', $data['fandoms']);
    $collection->set('field_relationship', $data['relationships']);
    $collection->set('field_rating', $data['ratings']);
    $collection->set('field_category', $data['categories']);
    $collection->set('field_format', $data['format_info']);
    $collection->set('field_warning', $data['warnings']);
    $collection->set('field_languages', $data['languages']);
    $duration_interval = $this->secondsToDateInterval($data['duration']);
    $collection->field_duration = ['duration' => $duration_interval, 'seconds' => $data['duration']];
    $collection->set('field_archive_lock', $data['archive_locked']);
    if ($save) {
      $collection->save();
    }
  }

  /**
   * Converts a total seconds value to an array containing HH:MM:SS string values.
   */
  public function secondsToHMS(int $total_seconds): array {
    return [
      'hours' => str_pad(floor($total_seconds / 3600), 2, '0', STR_PAD_LEFT),
      'mins' => str_pad(floor(floor($total_seconds / 60) % 60), 2, '0', STR_PAD_LEFT),
      'seconds' => str_pad($total_seconds % 60, 2, '0', STR_PAD_LEFT),
    ];
  }

  /**
   * Checks if a node is archive locked or not.
   *
   * Nodes without a archive lock field are never locked.
   */
  public function isNodeArchiveLocked(NodeInterface $node): bool {
    if (!$node->hasField('field_archive_lock')) {
      return FALSE;
    }

    return (bool) $node->get('field_archive_lock')->value;
  }

  /**
   * Collects and collates the data of an array of works.
   */
  private function fetchWorkData(array $works, ?int $updated_work_id, int $updated_work_duration_seconds) {
    $data = [
      'authors' => [],
      'readers' => [],
      'owners' => [],
      'fandoms' => [],
      'relationships' => [],
      'ratings' => [],
      'categories' => [],
      'format_info' => [],
      'warnings' => [],
      'languages' => [],
      'duration' => 0,
      'archive_locked' => [],
    ];

    /** @var \Drupal\node\NodeInterface $work */
    foreach ($works as $work) {
      $data['authors'] = array_merge($data['authors'], $work->get('field_author')->referencedEntities());
      $data['readers'] = array_merge($data['readers'], $work->get('field_reader')->referencedEntities());
      $data['owners'] = array_merge($data['owners'], $work->get('field_owner')->referencedEntities());
      $data['fandoms'] = array_merge($data['fandoms'], $work->get('field_fandom2')->referencedEntities());
      $data['relationships'] = array_merge($data['relationships'], $work->get('field_relationship')->referencedEntities());
      $data['ratings'] = array_merge($data['ratings'], $work->get('field_rating')->referencedEntities());
      $data['format_info'] = array_merge($data['format_info'], $work->get('field_format')->referencedEntities());
      $data['warnings'] = array_merge($data['warnings'], $work->get('field_warning')->referencedEntities());
      $data['archive_locked'][] = $this->isNodeArchiveLocked($work);

      if ($work->hasField('field_category')) {
        $data['categories'] = array_merge($data['categories'], $work->get('field_category')->referencedEntities());
      }

      $lang_key = $work->hasField('field_language') ? 'field_language' : 'field_languages';
      $data['languages'] = array_merge($data['languages'], $work->get($lang_key)->referencedEntities());

      if ($work->id() === strval($updated_work_id)) {
        $data['duration'] += $updated_work_duration_seconds;
      } elseif ($work->hasField('field_duration')) {
        $found = FALSE;
        foreach ($work->get('field_duration') as $duration_field) {
          $data['duration'] += $duration_field->seconds;
          $found = TRUE;
          break;
        }

        // If a legacy work does not have a duration set, calculate it from
        // the length tag.
        if (!$found && $work->getType() == 'legacy_work') {
          foreach ($work->get('field_length')->referencedEntities() as $length_tag) {
            $work_duration = $this->convertLengthRangeToDuration($length_tag);
            if (!empty($work_duration)) {
              $data['duration'] += $work_duration;
              break;
            }
          }
        }
      }
    }

    foreach ($data as $key => $value) {
      if ($key == 'duration' || $key == 'archive_locked') {
        continue;
      }
      $data[$key] = $this->removeDuplicateEntities($data[$key]);
    }

    $rating_value = [
      "General" => 0,
      "Teen and up" => 1,
      "Mature" => 2,
      "Explicit" => 3,
      "Not rated" => 4,
    ];

    $rating = NULL;
    foreach ($data['ratings'] as $r) {
      if ($rating === NULL or
          $rating_value[$rating->name->value] < $rating_value[$r->name->value]
        ) {
        $rating = $r;
      }
    }
    $data['ratings'] = [$rating];

    if (!in_array(FALSE, $data['archive_locked'])) {
      $data['archive_locked'] = TRUE;
    } else {
      $data['archive_locked'] = FALSE;
    }

    return $data;
  }

}
