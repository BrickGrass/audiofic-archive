<?php

namespace Drupal\audiofic_archive_rss\Hook;

use Drupal\aa_utils\Service\AudioficTagUtils;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\taxonomy\Entity\Term;

/**
 * Class AudioficArchiveRssTokenHooks defines the tokens used for RSS views.
 */
class AudioficArchiveRssTokenHooks {
  use StringTranslationTrait;

  const FILTER_KEYS = [
    'search_api_fulltext', 'field_completion_status', 'field_rating',
    'field_category', 'field_format', 'field_language', 'field_length',
    'field_warning', 'field_author', 'field_reader', 'field_fandom2',
    'field_relationship',
  ];

  public function __construct(
    protected readonly AudioficTagUtils $tag_utils,
  ) {}

  /**
   * Implements hook_token_info().
   */
  #[Hook('token_info')]
  public function tokenInfo() {
    return [
      'types' => [
        'contextual-filter-node' => [
          'name' => $this->t('Contextual Filter Node'),
          'description' => $this->t('The node selected by the view contextual filter'),
          'needs-data' => 'contextual_filter_node',
        ],
        'contextual-filter-term' => [
          'name' => $this->t('Contextual Filter Taxonomy Term'),
          'description' => $this->t('The Taxonomy Term selected by the contextual filter'),
          'needs-data' => 'contextual_filter_term',
        ],
        'exposed-filters' => [
          'name' => $this->t('Exposed Filters'),
          'description' => $this->t('The exposed filters currently applied by the user to this view'),
          'needs-data' => 'view',
        ],
      ],
      'tokens' => [
        'contextual-filter-node' => [
          'title' => ['name' => $this->t('Title')],
          'tags' => ['name' => $this->t('Tags')],
          'link' => ['name' => $this->t('Link')],
        ],
        'contextual-filter-term' => [
          'title' => ['name' => $this->t('Title')],
          'link' => ['name' => $this->t('Link')],
        ],
        'exposed-filters' => [
          'top-4-filters' => ['name' => $this->t('The top 4 most important filters applied')],
          'filter-summary' => ['name' => $this->t('A formatted summary of all the filters applied')],
          'spacer' => ['name' => $this->t("Prints a '|' character if any filters are present")],
        ],
      ],
    ];
  }

  /**
   * Implements hook_tokens() for the RSS module.
   */
  #[Hook('tokens')]
  public function tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata): array {
    $replacements = [];

    switch ($type) {
      case 'contextual-filter-node':
        $this->nodeTokens($tokens, $data, $replacements);
        break;

      case 'contextual-filter-term':
        $this->termTokens($tokens, $data, $replacements);
        break;

      case 'exposed-filters':
        $this->exposedFilterTokens($tokens, $data, $replacements);
        break;

      default:
        break;
    }

    return $replacements;
  }

  /**
   * Populates node tokens.
   */
  private function nodeTokens(array $tokens, array $data, array &$replacements) {
    if (!array_key_exists('contextual_filter_node', $data)) {
      return;
    }

    /** @var \Drupal\node\Entity\Node $node */
    $node = $data['contextual_filter_node'];
    foreach ($tokens as $key => $token) {
      switch ($key) {
        case 'title':
          $replacements[$token] = $node->getTitle();
          break;

        case 'tags':
          $replacements[$token] = $this->tag_utils->allNodeTagsToString($node);
          break;

        case 'link':
          $replacements[$token] = $node->toUrl()->toString();
          break;
      }
    }
  }

  /**
   * Populates term tokens.
   */
  private function termTokens(array $tokens, array $data, array &$replacements) {
    if (!array_key_exists('contextual_filter_term', $data)) {
      return;
    }

    /** @var \Drupal\taxonomy\Entity\Term $term */
    $term = $data['contextual_filter_term'];
    foreach ($tokens as $key => $token) {
      switch ($key) {
        case 'title':
          $replacements[$token] = $term->name->value;
          break;

        case 'link':
          $replacements[$token] = $term->toUrl()->toString();
          break;
      }
    }
  }

  /**
   * Populates exposed filter tokens.
   */
  private function exposedFilterTokens(array $tokens, array $data, array &$replacements) {
    if (!array_key_exists('view', $data)) {
      return;
    }

    $filters = $data['view']->filter;
    $filter_data = [];

    foreach (AudioficArchiveRssTokenHooks::FILTER_KEYS as $key) {
      if (!array_key_exists($key, $filters)) {
        continue;
      }

      if ($key === 'field_completion_status') {
        // only_complete = On is All is not selected, Off is all is selected.
        $filter_data[$key] = $filters[$key]->group_info !== 'All';
      } else {
        $filter_data[$key] = $filters[$key]->value;
      }
    }

    foreach ($tokens as $key => $token) {
      switch ($key) {
        case 'top-4-filters':
          // TODO: this seems unimplemented? What was the intention here?
          break;

        case 'spacer':
          if (!empty(array_filter($filter_data))) {
            $replacements[$token] = "|";
          }
          break;

        case 'filter-summary':
          $replacements[$token] = $this->generateFilterSummary($filter_data);
          break;
      }
    }
  }

  /**
   * Generate summary of applied filters.
   */
  private function generateFilterSummary($filter_data): string {
    $formatted = [];
    $max = 50;
    $labels = [
      'search_api_fulltext' => $this->t("Search Query"),
      'field_completion_status' => $this->t("Completion Status"),
      'field_rating' => $this->t("Rating"),
      'field_category' => $this->t("Category/Categories"),
      'field_format' => $this->t("Format Info"),
      'field_language' => $this->t("Language(s)"),
      'field_length' => $this->t("Length(s)"),
      'field_warning' => $this->t("Warning(s)"),
      'field_author' => $this->t("Author(s)"),
      'field_reader' => $this->t("Reader(s)"),
      'field_fandom2' => $this->t("Fandom(s)"),
      'field_relationship' => $this->t("Relationship(s)"),
    ];

    foreach ($filter_data as $key => $value) {
      switch ($key) {
        case 'search_api_fulltext':
          $filter_str = $value;
          break;

        case 'field_completion_status':
          $filter_str = $value ? $this->t('Only complete works') : '';
          break;

        default:
          $terms = array_map(fn($t) => $t->name->value, Term::loadMultiple($value));
          $filter_str = implode(', ', $terms);
          break;
      }

      if (empty($filter_str)) {
        continue;
      }

      $filter_str = strlen($filter_str) > $max + 3 ? substr($filter_str, 0, $max) . '...' : $filter_str;
      $formatted[] = sprintf('%s: %s', $labels[$key], $filter_str);
    }

    return implode("\n", $formatted);
  }

}
