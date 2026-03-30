<?php

namespace Drupal\aa_utils\Service;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;

/**
 * Class AudioficTagUtils holds utility functions for node tags.
 */
class AudioficTagUtils {
  use StringTranslationTrait;

  /**
   * Fetches all terms from a field on a node.
   */
  public function fetchFieldTerms(NodeInterface $node, string $field_name): array {
    if (!$node->hasField($field_name)) {
      return [];
    }

    return $node->get($field_name)->referencedEntities();
  }

  /**
   * Fetches and converts tag names into a comma separated list.
   *
   * The total no tags can be truncated if desired.
   */
  public function nodeTagsToString(
    NodeInterface $node,
    string $field_name,
    ?int $truncate_to = NULL,
  ): string {
    $all_tags = array_map(
      fn ($t) => $t->name->value,
      $this->fetchFieldTerms($node, $field_name)
    );

    if ($truncate_to !== NULL && count($all_tags) > $truncate_to) {
      $all_tags = array_slice($all_tags, 0, $truncate_to);
      $all_tags[$truncate_to - 1] = $all_tags[$truncate_to - 1] . '...';
    }

    return implode(', ', $all_tags);
  }

  /**
   * Summarises all main metadata on a work into a formatted string.
   */
  public function allNodeTagsToString(NodeInterface $node): string {
    $tags = [
      'Warning(s): ' => $this->nodeTagsToString($node, 'field_warning'),
      'Fandom(s): ' => $this->nodeTagsToString($node, 'field_fandom2', 10),
      'Relationship(s): ' => $this->nodeTagsToString($node, 'field_relationship', 10),
      'Category/Categories: ' => $this->nodeTagsToString($node, 'field_category'),
      'Format Info: ' => $this->nodeTagsToString($node, 'field_format'),
    ];

    $text = [];
    foreach ($tags as $label => $content) {
      if (!empty($content)) {
        $text[] = $this->t($label) . $content;
      }
    }

    return implode("\n", $text);
  }

}
