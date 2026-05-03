<?php

namespace Drupal\aa_utils\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Block that displays a live counter of the content on the archive.
 *
 * It shows a count of the NO:
 *  - works created
 *  - canonical fandoms created
 *  - user accounts registered
 *
 * @Block(
 *   id = "content_count",
 *   admin_label = @Translation("Content Count Block"),
 *   category = @Translation("Blocks"),
 * )
 */
class ContentCountBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityTypeManagerInterface $entity_type_manager,
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
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $work_count = $this->entity_type_manager->getStorage('node')->getQuery()
      ->condition('type', ['work', 'legacy_work'], 'IN')
      ->condition('status', 1)
      ->count()
      ->execute();

    $fandom_count = $this->entity_type_manager->getStorage('taxonomy_term')->getQuery()
      ->accessCheck(TRUE)
      ->condition('vid', 'fandom')
      ->condition('field_canonicity', 'canon')
      ->count()
      ->execute();

    $user_count = $this->entity_type_manager->getStorage('user')->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 1)
      ->condition('roles.target_id', ['content_editor'], 'IN')
      ->count()
      ->execute();

    return [
      '#theme' => 'content-count',
      '#work_count' => $work_count,
      '#fandom_count' => $fandom_count,
      '#user_count' => $user_count,
      // Cache record to expire after 6 hours.
      '#cache' => [
        'contexts' => ['route'],
        'max-age' => 21600,
      ],
    ];
  }

}
