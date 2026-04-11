<?php

namespace Drupal\audiofic_archive_player\Hook;

use Drupal\aa_utils\Service\AudioficUtils;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\node\NodeInterface;
use Drupal\file\Entity\File;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Class AudioficArchivePlayerThemeHooks processes data for the player.
 */
class AudioficArchivePlayerThemeHooks {
  use StringTranslationTrait;

  public function __construct(
    protected AudioficUtils $utils,
  ) {}

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme($existing, $type, $theme, $path): array {
    return [
      'audiofic_archive_player' => [
        'template' => 'audiofic_archive_player',
        'path' => $path . '/templates',
        'initial preprocess' => static::class . ':preprocessViewsViewAudioficArchivePlayer',
      ],
    ];
  }

  /**
   * Implements template_preprocess_views_view() for audiofic_archive_player.
   */
  public function preprocessViewsViewAudioficArchivePlayer(&$variables) {
    $options = $variables['view']->style_plugin->options;

    $streaming_files = [];
    $other_files = [];
    foreach ($variables['view']->result as $result) {
      $entity = $result->_entity;
      if ($entity->getType() !== 'work') {
        continue;
      }
      $this->fetchMediaFiles($entity, 'mp3', $options['is_series'], $streaming_files);
      $this->fetchMediaFiles($entity, 'other', $options['is_series'], $other_files);
    }

    $all_nsfw = TRUE;
    foreach ($streaming_files as $file) {
      if ($file['nsfw'] === FALSE) {
        $all_nsfw = FALSE;
        break;
      }
    }

    $options['all_nsfw'] = $all_nsfw;
    $options['streaming_files'] = $streaming_files;
    $options['other_files'] = $other_files;
    $variables['options'] = $options;
    $variables['view']->element['#attached']['library'][] = 'audiofic_archive_player/playlist';
  }

  /**
   * Fetches the media files from a node and adds them to $files.
   */
  private function fetchMediaFiles(NodeInterface $node, string $media_type, bool $is_series, array &$files) {
    $field_name = $media_type === 'mp3' ? 'field_mp3_files' : 'field_other_files';

    if (!$node->hasField($field_name)) {
      return;
    }

    $work_files = $node->get($field_name)->referencedEntities();
    $i = 1;
    $total = count($work_files);
    foreach ($work_files as $file) {
      $phys_file = NULL;
      $label = NULL;
      if ($media_type === 'mp3') {
        $phys_file = File::load($file->field_media_audio_file[0]->target_id);
        $label = $file->get('field_chapter_label');
      } else {
        $phys_file = File::load($file->field_media_file[0]->target_id);
        $label = $file->get('field_file_label');
      }

      $data = [
        'name' => $this->getMediaLabel($node, $label, $is_series, $i, $total),
        'file_size' => $this->makeBytesReadable($phys_file->filesize->value),
        'mime_type' => $this->getFileType($phys_file->filemime->value),
        'url' => $phys_file->createFileUrl(),
        'nsfw' => $this->utils->isNsfw($node),
      ];

      $files[] = $data;
      $i++;
    }
  }

  private function getMediaLabel(NodeInterface $work, FieldItemListInterface $label_field, bool $is_series, int $part, int $total): string {
    $user_label = count($label_field) > 0 ? $label_field[0]->value : '';
    $user_label_empty = strlen(trim($user_label)) < 1;
    $label_parts = [];

    if ($user_label_empty || $is_series) {
      $label_parts[] = $work->getTitle();
    }

    if ($user_label_empty) {
      if ($total > 1) {
        $label_parts[] = $this->t('Part @part of @total', ['@part' => $part, '@total' => $total]);
      }
    } else {
      $label_parts[] = $user_label;
    }

    return trim(implode(' - ', $label_parts));
  }

  /**
   * Converts bytes to a human readable format.
   *
   * Adapted from: https://gist.github.com/liunian/9338301.
   */
  private function makeBytesReadable($bytes) {
    $i = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), [0, 0, 0, 0, 0][$i]) . ['B', 'kB', 'MB', 'GB', 'TB'][$i];
  }

  /**
   * Converts a mime type to a human readable filetype.
   */
  private function getFileType(string $mime_type): string {
    switch ($mime_type) {
      case "audio/mpeg":
        return "MP3";

      case "audio/wav":
        return "WAV";

      case "audio/webm":
        return "WEBM Audio";

      case "audio/m4a":
        return "M4A";

      case "audio/m4b":
        return "M4B";

      case "application/pdf":
        return "PDF";

      case "application/zip":
        return "ZIP";

      case "image/jpeg":
        return "JPEG";

      case "image/png":
        return "PNG";

      case "image/webp":
        return "WEBP";

      case "text/plain":
        return "TXT";

      default:
        return "UNKNOWN";
    }
  }

}
