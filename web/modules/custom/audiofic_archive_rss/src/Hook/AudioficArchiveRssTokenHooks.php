<?php

namespace Drupal\audiofic_archive_rss\Hook;

use Drupal\aa_utils\Service\AudioficTagUtils;
use Drupal\aa_utils\Service\AudioficUtils;
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
    // OTHER:
    'search_api_fulltext', 'field_completion_status', 'field_duration_seconds',
    'field_duration_seconds_1',
    // INCLUDE:
    'field_rating', 'field_category', 'field_format', 'field_language',
    'field_warning', 'field_author', 'field_reader', 'field_fandom2',
    'field_relationship',
    // EXCLUDE:
    'field_rating_1', 'field_category_1', 'field_format_1', 'field_language_1',
    'field_warning_1', 'field_author_1', 'field_fandom2_1', 'field_reader_1',
    'field_relationship_1',
  ];

  public function __construct(
    protected AudioficUtils $utils,
    protected AudioficTagUtils $tag_utils,
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
          $text = [];
          foreach ($this->tag_utils->allNodeTagsToString($node) as $label => $content) {
            $text[] = $label . $content;
          }
          $replacements[$token] = implode("\n", $text);
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
   * Fetches all search exposed filter values set by the user.
   */
  public static function getExposedFilterData(array $filters): array {
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

    return $filter_data;
  }

  /**
   * Populates exposed filter tokens.
   */
  private function exposedFilterTokens(array $tokens, array $data, array &$replacements) {
    if (!array_key_exists('view', $data)) {
      return;
    }
    $filter_data = $this->getExposedFilterData($data['view']->filter);

    foreach ($tokens as $key => $token) {
      switch ($key) {
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
      'search_api_fulltext' => $this->t('Search Query'),
      'field_completion_status' => $this->t('Completion Status'),
      'field_duration_seconds' => $this->t('Minimum Duration'),
      'field_duration_seconds_1' => $this->t('Maximum Duration'),
      'field_rating' => $this->t('Include Rating'),
      'field_category' => $this->t('Include Category/Categories'),
      'field_format' => $this->t('Include Format Info'),
      'field_language' => $this->t('Include Language(s)'),
      'field_warning' => $this->t('Include Warning(s)'),
      'field_author' => $this->t('Include Author(s)'),
      'field_reader' => $this->t('Include Reader(s)'),
      'field_fandom2' => $this->t('Include Fandom(s)'),
      'field_relationship' => $this->t('Include Relationship(s)'),
      'field_rating_1' => $this->t('Exclude Rating'),
      'field_category_1' => $this->t('Exclude Category/Categories'),
      'field_format_' => $this->t('Exclude Format Info'),
      'field_language_1' => $this->t('Exclude Language(s)'),
      'field_warning_1' => $this->t('Exclude Warning(s)'),
      'field_author_1' => $this->t('Exclude Author(s)'),
      'field_reader_1' => $this->t('Exclude Reader(s)'),
      'field_fandom2_1' => $this->t('Exclude Fandom(s)'),
      'field_relationship_1' => $this->t('Exclude Relationship(s)'),
    ];

    foreach ($filter_data as $key => $value) {
      switch ($key) {
        case 'search_api_fulltext':
          $filter_str = $value;
          break;

        case 'field_completion_status':
          $filter_str = $value ? $this->t('Only complete works') : '';
          break;

        case 'field_duration_seconds':
        case 'field_duration_seconds_1':
          $duration_value = array_first($value);
          $filter_str = !empty($duration_value) ? implode(':', $this->utils->secondsToHms((int) $duration_value)) : '';
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

    if (count($formatted) > 0) {
      array_unshift($formatted, $this->t('Applied Filters:'));
    }

    return implode("\n", $formatted);
  }

}
