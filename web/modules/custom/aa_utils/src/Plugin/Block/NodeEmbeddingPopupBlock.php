<?php

namespace Drupal\aa_utils\Plugin\Block;

use Drupal\aa_utils\Service\AudioficUtils;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Displays a popup containing direct links for the work author.
 *
 * Direct links to all of the work media (cover, streaming files, other files)
 * along with exemplar html for embedding that media on other websites.
 *
 * At some point this will be customisable via user settings (eg: we generate
 * the exact posting html that the user uses on other sites!)
 */
#[Block(
  id: "node_embedding_popup",
  admin_label: new TranslatableMarkup('Node media embedding popup block'),
  category: new TranslatableMarkup('Blocks'),
  context_definitions: [
    'node' => new EntityContextDefinition(
      data_type: 'entity:node',
      label: new TranslatableMarkup('Node'),
    ),
  ]
)]
class NodeEmbeddingPopupBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityTypeManagerInterface $entity_type_manager,
    protected AccountInterface $current_user,
    protected AudioficUtils $utils,
    protected FileUrlGeneratorInterface $file_url_generator,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('aa_utils.utils'),
      $container->get('file_url_generator'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->getContextValue('node');
    if ($node->getType() != 'work') {
      return [];
    }

    $user = $this->entity_type_manager->getStorage('user')->load($this->current_user->id());
    if (!$node->access('update', $user)) {
      return [
        '#cache' => [
          'contexts' => ['user'],
          'tags' => ['node:' . $node->id()],
        ],
      ];
    }

    $media_rows = [];

    $cover_data = [];
    $node_cover = $node->get('field_cover');
    if (!$node_cover->isEmpty()) {
      $url = $this->file_url_generator->generate($node_cover->entity->getFileUri());
      $url->setAbsolute(TRUE);
      $media_rows[] = [
        'type' => ['data' => ['#markup' => $this->t('Cover Art')]],
        'name' => ['data' => ['#markup' => $node_cover->alt]],
        'url' => ['data' => ['#markup' => '<code>' . $url->toString() . '</code>']],
      ];

      $cover_data = [
        'url' => $url->toString(),
        'alt' => $node_cover->alt,
      ];
    }
    $streaming_file_rows = $this->getMediaRows($node->get('field_mp3_files')->referencedEntities(), TRUE);
    $other_file_rows = $this->getMediaRows($node->get('field_other_files')->referencedEntities(), FALSE);
    $media_rows = array_merge($media_rows, $streaming_file_rows, $other_file_rows);

    $media_table = [
      '#type' => 'table',
      '#header' => [
        'type' => $this->t('Media Type'),
        'name' => $this->t('Label/Alt Text'),
        'url' => $this->t('Direct URL'),
      ],
      '#rows' => $media_rows,
    ];

    $streaming_file_data = [];
    foreach ($node->get('field_mp3_files')->referencedEntities() as $media) {
      /** @var \Drupal\file\FileInterface $file */
      $file = $this->entity_type_manager->getStorage('file')->load($media->field_media_audio_file[0]->target_id);
      $url = $this->file_url_generator->generate($file->getFileUri());
      $url->setAbsolute(TRUE);

      $streaming_file_data[] = [
        'url' => $url->toString(),
      ];
    }

    return [
      '#theme' => 'node-embedding-popup',
      '#media_table' => $media_table,
      '#cover_data' => $cover_data,
      '#streaming_file_data' => $streaming_file_data,
      '#cache' => [
        'contexts' => ['user'],
        'tags' => ['node:' . $node->id()],
      ],
    ];
  }

  /**
   * Fetch all of a type of media and build table rows from it.
   */
  private function getMediaRows(array $all_media, bool $streaming): array {
    $rows = [];

    $i = 1;
    foreach ($all_media as $media) {
      if ($streaming) {
        /** @var \Drupal\file\FileInterface $file */
        $file = $this->entity_type_manager->getStorage('file')->load($media->field_media_audio_file[0]->target_id);
      } else {
        /** @var \Drupal\file\FileInterface $file */
        $file = $this->entity_type_manager->getStorage('file')->load($media->field_media_file[0]->target_id);
      }
      $label = $media->get($streaming ? 'field_chapter_label' : 'field_file_label');
      $label = count($label) > 0 && strlen($label[0]->value) > 0 ? $label[0]->value : NULL;
      if (empty($label) && $streaming) {
        $label = $this->t('Chapter @part', ['@part' => $i]);
      } elseif (empty($label) && !$streaming) {
        $label = $this->t('Other file @part', ['@part' => $i]);
      }
      $url = $this->file_url_generator->generate($file->getFileUri());
      $url->setAbsolute(TRUE);
      $rows[] = [
        'type' => ['data' => ['#markup' => $streaming ? $this->t('Streaming File') : $this->t('Other File')]],
        'name' => ['data' => ['#markup' => $label]],
        'url' => ['data' => ['#markup' => '<code>' . $url->toString() . '</code>']],
      ];

      $i++;
    }

    return $rows;
  }

}
