<?php

namespace Drupal\audiofic_archive_rss\Hook;

use Drupal\aa_utils\Service\AudioficUtils;
use Drupal\aa_utils\Service\AudioficTagUtils;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Utility\Token;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\StringTranslation\StringTranslationTrait;

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

    $this->setRssContextualFilterData($contextual_filters, $variables, $token_data);
    $override_media_pubdate = $variables['contextual_filter'] !== 'none' && $options['override_media_pubdate'];

    $options['title'] = $this->token->replace($options['title'], $token_data, ['clear' => TRUE]);
    $options['description'] = $this->token->replace($options['description'], $token_data, ['clear' => TRUE]);
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
        'summary' => $this->fetchSummary($entity),
        'cover_url' => $this->fetchCover($entity),
        'link' => $entity->toUrl(),
      ];
    }

    $variables['options'] = $options;
    $variables['works'] = $works;
    $variables['hostname'] = $this->request_stack->getCurrentRequest()->getHost();
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
    return count($cover_field) > 0 ? $cover_field[0]->createFileUrl() : NULL;
  }

  /**
   * Fetches an array containing each of the streaming files on a work.
   */
  private function fetchStreamingFiles(NodeInterface $work, bool $sort_by_chapter): array {
    if (count($mp3_files = $work->get('field_mp3_files')->referencedEntities()) < 1) {
      return [];
    }

    $total_parts = count($mp3_files);
    $i = 1;

    $work_files = [];
    foreach ($mp3_files as $file) {
      $phys_file = File::load($file->field_media_audio_file[0]->target_id);
      $label = $file->get('field_chapter_label');
      $label = !empty($label) && strlen($label[0]->value) > 0 ? $label[0]->value : '';
      $duration = $file->get('field_duration_seconds');

      $part_no = $total_parts > 1 ? $this->t('@part_label (Part @part of @total)', [
        '@part' => $i,
        '@total' => $total_parts,
        '@part_label' => !empty($label) ? ': ' . $label : '',
      ]) : '';
      $link = '<p><a href="' . $work->toUrl()->toString() . '">' . $work->getTitle() . $part_no . '</a></p>';

      $work_files[] = [
        'name' => $label,
        'file_size' => $phys_file->filesize->value,
        'mime_type' => $phys_file->filemime->value,
        'url' => $phys_file->createFileUrl(),
        'uuid' => $file->uuid(),
        'duration' => !empty($duration) ? $duration[0]->value : 0,
        'date_created' => $file->getCreatedTime(),
        'part_no' => $link,
      ];
      $i++;
    }

    if (!$sort_by_chapter) {
      return $work_files;
    }

    // To have the chapters of a work appear sequentially in any of
    // our feeds, they need to have sequential publishing dates.
    // This only ensures that a works chapters appear in order -
    // it does not fix a series appearing in the wrong order because
    // it was uploaded non-sequentially, as that would involve changing
    // the pubdate of items constantly causing confusion in podcast clients!
    $date_created = $work->getCreatedTime();
    $i = 0;
    foreach ($work_files as $file_data) {
      $file_data['date_created'] = $date_created + $i;
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
