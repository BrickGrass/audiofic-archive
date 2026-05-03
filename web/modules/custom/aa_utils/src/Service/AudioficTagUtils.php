<?php

namespace Drupal\aa_utils\Service;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Class AudioficTagUtils holds utility functions for node tags.
 */
class AudioficTagUtils {
  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  public function __construct(EntityTypeManager $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

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
   * Summarises all main metadata on a work into an array.
   */
  public function allNodeTagsToString(NodeInterface $node): array {
    $tags = [
      'Read by: ' => $this->nodeTagsToString($node, 'field_reader'),
      'Written by: ' => $this->nodeTagsToString($node, 'field_author'),
      'Cover art created by: ' => $this->nodeTagsToString($node, 'field_cover_artist'),
      'Warning(s): ' => $this->nodeTagsToString($node, 'field_warning'),
      'Fandom(s): ' => $this->nodeTagsToString($node, 'field_fandom2', 10),
      'Relationship(s): ' => $this->nodeTagsToString($node, 'field_relationship', 10),
      'Category/Categories: ' => $this->nodeTagsToString($node, 'field_category'),
      'Format Info: ' => $this->nodeTagsToString($node, 'field_format'),
    ];
    foreach ($tags as $label => $content) {
      if (empty($content)) {
        unset($tags[$label]);
      }
    }

    return $tags;
  }

  /**
   * Checks if a term has canonicity data.
   */
  public function isTagCanonicityAware(TermInterface $term): bool {
    return $term->hasField('field_canonicity') && $term->hasField('field_canon_sibling');
  }

  /**
   * Fetch the ids of all of the root fandoms.
   */
  public function getRootFandomIds(): array {
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    return $term_storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('vid', 'fandom')
      ->condition('field_canonicity', ['canonical_root_fandom'], 'IN')
      ->execute();
  }

}
