<?php

namespace Drupal\aa_search\Hook;

use Drupal\aa_utils\Service\AudioficTagUtils;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\search_api\Hook\ContentEntityDatasourceHooks;
use Drupal\search_api\Hook\SearchApiHooks;
use Drupal\taxonomy\TermInterface;

/**
 * Class AASearchThemeHooks defines hooks for the aa_search module.
 */
class AASearchHooks {

  public function __construct(
    protected EntityTypeManagerInterface $entity_type_manager,
    protected AudioficTagUtils $tag_utils,
  ) {}

  /**
   * Implements hook_ENTITY_TYPE_update() for taxonomy terms.
   *
   * Ensures that when a canonical term is updated, search api tracking
   * is notified that it needs to reindex any synonyms of that term,
   * any children, grandchildren etc, and each of their synonyms also!
   */
  #[Hook('taxonomy_term_update')]
  public function taxonomyTermUpdate(TermInterface $term) {
    $this->propagateCanonicalUpdate($term, TRUE);
  }

  /**
   * Propagates an update to a canonical term to each of it's synonyms.
   *
   * Then ensures that this function is called for each of that term's
   * children.
   */
  private function propagateCanonicalUpdate(TermInterface $term, bool $source = FALSE) {
    if (!$this->tag_utils->isTagCanonicityAware($term)) {
      return;
    }

    // Schedule for indexing itself if not originator.
    if (!$source) {
      \Drupal::getContainer()->get(ContentEntityDatasourceHooks::class)->entityUpdate($term);
      \Drupal::getContainer()->get(SearchApiHooks::class)->entityUpdate($term);
    }

    // Non canonicals shouldn't be in the hierarchy,
    // but if they are ignore them.
    $canonicity = $term->get('field_canonicity')->value;
    if (!in_array($canonicity, ['canon', 'canonical_root_fandom'])) {
      return;
    }

    // Reindex all synonyms.
    /** @var \Drupal\taxonomy\TermStorage $term_storage */
    $term_storage = $this->entity_type_manager->getStorage('taxonomy_term');
    $synonym_ids = $term_storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('vid', 'fandom')
      ->condition('field_canon_sibling.entity:taxonomy_term.tid', $term->id())
      ->execute();
    foreach ($term_storage->loadMultiple($synonym_ids) as $synonym) {
      \Drupal::getContainer()->get(ContentEntityDatasourceHooks::class)->entityUpdate($synonym);
      \Drupal::getContainer()->get(SearchApiHooks::class)->entityUpdate($synonym);
    }

    // Check all children.
    foreach ($term_storage->loadChildren($term->id()) as $child) {
      $this->propagateCanonicalUpdate($child);
    }
  }

}
