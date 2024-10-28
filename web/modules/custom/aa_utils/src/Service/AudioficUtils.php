<?php

namespace Drupal\aa_utils\Service;

use Drupal\node\NodeInterface;

/**
 * Class AudioficUtils.
 *
 * Provides utility functions to the audiofic archive codebase.
 */
class AudioficUtils {

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

}
