<?php

namespace Drupal\aa_utils\Service;

use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Class AudioficUtils.
 *
 * Provides utility functions to the audiofic archive codebase.
 */
class AudioficUtils {

  private const MINUTE = 60;
  private const HOUR = self::MINUTE * 60;
  private const LENGTHRANGEMAPPING = [
    'under 10 min' =>                   self::MINUTE * 5,
    '10-20 min'    =>                   self::MINUTE * 15,
    '20-30 min'    =>                   self::MINUTE * 25,
    '30-45 min'    =>                   self::MINUTE * 37,
    '45 min-1hr'   =>                   self::MINUTE * 52,
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
   * Fetches all the terms from a given vocabulary and adds them to all_terms.
   */
  public function fetchVocabTerms(NodeInterface $entity, string $vocab_name, array &$all_terms): void {
    if ($entity->hasField($vocab_name)) {
      foreach ($entity->get($vocab_name)->referencedEntities() as $term) {
        $all_terms[] = $term;
      }
    }
  }

  /**
   * Determines whether an entity is rated nsfw (no rating or null is nsfw).
   */
  public function isNsfw(NodeInterface $node): bool {
    if (!$node->hasField('field_rating')) {
      return TRUE;
    }

    foreach ($node->get("field_rating")->referencedEntities() as $rating) {
      if (in_array($rating->name->value, ["General", "Teen and up"])) {
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
   * Uses the id() menthod to calculate uniqueness.
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

}
