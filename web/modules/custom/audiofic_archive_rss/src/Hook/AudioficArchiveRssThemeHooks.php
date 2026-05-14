<?php

namespace Drupal\audiofic_archive_rss\Hook;

use Drupal\aa_utils\Service\AudioficUtils;
use Drupal\aa_utils\Service\AudioficTagUtils;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Link;
use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Utility\Token;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Path\CurrentPathStack;

/**
 * RSS View Hooks.
 */
class AudioficArchiveRssThemeHooks {
  use StringTranslationTrait;

  public function __construct(
    protected AudioficUtils $utils,
    protected AudioficTagUtils $tag_utils,
    protected Token $token,
    protected RequestStack $request_stack,
    protected CurrentPathStack $current_path,
  ) {}

  /**
   * Implements hook_theme().
   *
   * Register theming functions for the Audiofic RSS module.
   */
  #[Hook('theme')]
  public function theme($existing, $type, $theme, $path): array {
    return [
      'audiofic_archive_rss' => [
        'template' => 'audiofic_archive_rss',
        'path' => $path . '/templates',
        'initial preprocess' => static::class . ':preprocessViewsViewAudioficArchiveRss',
      ],
    ];
  }

  /**
   * Preprocesses Audiofic RSS feed view.
   */
  public function preprocessViewsViewAudioficArchiveRss(&$variables) {
    $options = $variables['view']->style_plugin->options;
    $token_data = ['view' => $variables['view']];
    $contextual_filters = $variables['view']->argument;

    $this->setPaginationData($variables);

    $this->setRssContextualFilterData($contextual_filters, $variables, $token_data);
    $override_media_pubdate = $variables['contextual_filter'] !== 'none' && $options['override_media_pubdate'];

    $options['title'] = $this->token->replacePlain($options['title'], $token_data, ['clear' => TRUE]);
    $options['description'] = $this->token->replacePlain($options['description'], $token_data, ['clear' => TRUE]);
    $options['link'] = $this->token->replace($options['link'], $token_data, ['clear' => TRUE]);

    $works = [];
    foreach ($variables['view']->result as $result) {
      /** @var \Drupal\node\NodeInterface $entity */
      $entity = $result->_entity;
      if ($entity->getType() !== 'work') {
        continue;
      }

      $works[] = [
        'title' => $entity->getTitle(),
        'explicit' => $this->utils->isNsfw($entity),
        'files' => $this->fetchStreamingFiles($entity, $override_media_pubdate),
        'tags' => $this->tag_utils->allNodeTagsToString($entity),
        'summary' => check_markup($this->fetchSummary($entity), 'basic_html'),
        'cover_url' => $this->fetchCover($entity),
        'link' => $entity->toUrl(),
      ];
    }

    if (!$options['is_contextual'] || $variables['contextual_filter'] != 'node') {
      $variables['explicit'] = $this->doFiltersAllowExplicitWorks($variables);
    }

    $variables['options'] = $options;
    $variables['works'] = $works;
    $variables['hostname'] = $this->request_stack->getCurrentRequest()->getHost();
  }

  /**
   * Fetches the pagination links for this feed (if available).
   */
  private function setPaginationData(&$variables) {
    /** @var \Drupal\views\Plugin\views\pager\Full $pager */
    $pager = $variables['view']->pager;
    $current_page = $pager->getCurrentPage();

    if ($current_page == NULL) {
      return;
    }

    $host = $this->request_stack->getCurrentRequest()->getSchemeAndHttpHost();
    $path = $this->current_path->getPath();
    $queryParams = $this->request_stack->getCurrentRequest()->query->all();

    if (array_key_exists('page', $queryParams)) {
      unset($queryParams['page']);
    }

    $variables['pagination'] = [
      'self' => $this->generatePageUrl($host, $path, $queryParams, $current_page),
      'first' => $this->generatePageUrl($host, $path, $queryParams, 0),
      'last' => $this->generatePageUrl($host, $path, $queryParams, $pager->getPagerTotal()),
      'next' => $this->generatePageUrl($host, $path, $queryParams, $current_page + 1),
    ];

    if ($current_page > 0) {
      $variables['pagination']['previous'] = $this->generatePageUrl($host, $path, $queryParams, $current_page - 1);
    }
  }

  /**
   * Generates a url from a host + path + query params + page no.
   */
  private function generatePageUrl(string $host, string $path, array $queryParams, int $page): string {
    $queryParams['page'] = $page;
    return $host . $path . '?' . http_build_query($queryParams);
  }

  /**
   * Checks whether the current filters allow for works with an Explicit, Mature or Unrated rating.
   */
  private function doFiltersAllowExplicitWorks(array $variables): bool {
    $filter_data = AudioficArchiveRssTokenHooks::getExposedFilterData($variables['view']->filter);
    $include_rating = NULL;
    $exclude_rating = NULL;

    foreach ($filter_data as $key => $value) {
      switch ($key) {
        case 'field_rating':
          $include_rating = array_map(fn($t) => $t->name->value, Term::loadMultiple($value));
          break;

        case 'field_rating_1':
          $exclude_rating = array_map(fn($t) => $t->name->value, Term::loadMultiple($value));
          break;
      }
    }

    if (array_any(['Teen and up', 'General'], fn($t) => in_array($t, $include_rating)) &&
        array_all(['Mature', 'Explicit', 'Not rated'], fn($t) => !in_array($t, $include_rating))) {
      return FALSE;
    }

    if (array_all(['Mature', 'Explicit', 'Not rated'], fn($t) => in_array($t, $exclude_rating))) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Modifies $variables and $token_data to contain contextual filter data.
   *
   * If no contextual filters are set/one is set but contains an entity that
   * doesn't exist, no contextual filters are set.
   */
  private function setRssContextualFilterData(array $contextual_filters, array &$variables, array &$token_data) {
    $argument = array_first($contextual_filters);
    $argument_type = $argument ? $argument->options['default_argument_type'] : NULL;

    switch ($argument_type) {
      case 'node':
        if (!$node = Node::load($argument->argument)) {
          break;
        }
        $authors = $this->tag_utils->nodeTagsToString($node, "field_author");
        $readers = $this->tag_utils->nodeTagsToString($node, "field_reader");

        $token_data['contextual_filter_node'] = $node;
        $variables['contextual_filter'] = 'node';
        $variables['source_node'] = [
          "author" => "Read by: $readers | Written by: $authors",
          "cover_url" => $this->fetchCover($node),
          "explicit" => $this->utils->isNsfw($node),
        ];
        return;

      case 'taxonomy_tid':
        if (!$term = Term::load($argument->argument)) {
          break;
        }
        $token_data['contextual_filter_term'] = $term;
        $variables['contextual_filter'] = 'term';
        return;
    }

    $variables['contextual_filter'] = 'none';
  }

  /**
   * Fetches the URL of a nodes cover image, if set.
   */
  private function fetchCover(NodeInterface $node): NULL | string {
    $cover_field = $node->get('field_cover')->referencedEntities();
    if (empty($cover_field)) {
      return NULL;
    }

    // We use the extra_extra_large image style (1600x1600) to comply
    // with apple podcast standards, see:
    // https://podcasters.apple.com/support/5516-episode-art-template
    $cover_image = $cover_field[0]->getFileUri();
    return ImageStyle::load('extra_extra_large')->buildUrl($cover_image);
  }

  /**
   * Fetches an array containing each of the streaming files on a work.
   */
  private function fetchStreamingFiles(NodeInterface $work, bool $sort_by_chapter): array {
    if (count($mp3_files = $work->get('field_mp3_files')->referencedEntities()) < 1) {
      return [];
    }

    $date_created = $work->getCreatedTime();
    $total_parts = count($mp3_files);
    $i = 1;

    $work_files = [];
    foreach ($mp3_files as $file) {
      $phys_file = File::load($file->field_media_audio_file[0]->target_id);
      $label = $file->get('field_chapter_label');
      $label = count($label) > 0 && strlen($label[0]->value) > 0 ? $label[0]->value : '';
      $duration = $file->get('field_duration_seconds');

      $part_no = $total_parts > 1 ? $this->t('@part_label [Part @part of @total]', [
        '@part' => $i,
        '@total' => $total_parts,
        '@part_label' => !empty($label) ? ': ' . $label : '',
      ]) : '';
      $work_link = Link::fromTextAndUrl($work->getTitle() . $part_no, $work->toUrl());

      $series_links = [];
      /** @var \Drupal\node\NodeInterface $series */
      foreach ($work->get('field_series')->referencedEntities() as $series) {
        $j = 1;
        $series_works = $series->get('field_works_series')->referencedEntities();
        foreach ($series_works as $series_work) {
          if ($work->id() == $series_work->id()) {
            $series_label = $this->t('Part @part of @total in series @series', [
              '@part' => $j,
              '@total' => count($series_works),
              '@series' => $series->getTitle(),
            ]);
            $series_links[] = Link::fromTextAndUrl($series_label, $series->toUrl());
            break;
          }
          $i++;
        }
      }

      // To have the chapters of a work appear sequentially in any of
      // our feeds, they need to have sequential publishing dates.
      // This only ensures that a works chapters appear in order -
      // it does not fix a series appearing in the wrong order because
      // it was uploaded non-sequentially, as that would involve changing
      // the pubdate of items constantly causing confusion in podcast clients!
      $file_created = $sort_by_chapter ? $date_created + $i : $file->getCreatedTime();

      $work_files[] = [
        'name' => $part_no,
        'file_size' => $phys_file->filesize->value,
        'mime_type' => $phys_file->filemime->value,
        'url' => $phys_file->createFileUrl(),
        'uuid' => $file->uuid(),
        'duration' => !empty($duration) ? $duration[0]->value : 0,
        'date_created' => $file_created,
        'part_no' => $work_link,
        'series_links' => $series_links,
      ];
      $i++;
    }

    return $work_files;
  }

  /**
   * Fetches the summary field from a work, if it exists.
   */
  private function fetchSummary(NodeInterface $work): string {
    if ($summary = array_first($work->get('field_summary')->getValue())) {
      return $summary['value'];
    }
    return '';
  }

}
