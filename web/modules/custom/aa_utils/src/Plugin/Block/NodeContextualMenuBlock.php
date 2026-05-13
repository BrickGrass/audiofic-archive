<?php

namespace Drupal\aa_utils\Plugin\Block;

use Drupal\aa_utils\Service\AudioficUtils;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Displays Edit/Delete links on nodes that a user has access to edit.
 *
 * @Block(
 *   id = "node_contextual_menu",
 *   admin_label = @Translation("Node Contextual Menu Block"),
 *   category = @Translation("Menus"),
 *   context_definitions = {
 *     "node" = @ContextDefinition("entity:node", label = @Translation("Node"))
 *   }
 * )
 */
class NodeContextualMenuBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a NodeContextualMenuBlock object.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityTypeManagerInterface $entity_type_manager,
    protected AccountInterface $current_user,
    protected AudioficUtils $utils,
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
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $node = $this->getContextValue('node');
    $user = $this->entity_type_manager->getStorage('user')->load($this->current_user->id());
    $nid = $node->id();

    $can_remove_attribution = $this->utils->isUserAttributed($user, $node) &&
                              $node->getType() !== 'playlist' &&
                              $this->isWorkMultivoice($user, $node);

    return [
      '#theme' => 'node-contextual-menu',
      '#nid' => $nid,
      '#uid' => $this->current_user->id(),
      '#node_type' => $node->getType(),
      '#has_edit_access' => $node->access('update', $user),
      '#can_remove_attribution' => $can_remove_attribution,
      '#cache' => [
        'contexts' => ['user'],
        'tags' => ["node:{$nid}"],
      ],
    ];
  }

  /**
   * Checks whether users other than user are readers on the node.
   */
  private function isWorkMultivoice($user, $node): bool {
    if (!$user) {
      return FALSE;
    }

    $user_reader_tags = array_column($user->get('field_reader_name')->getValue(), 'target_id');
    $node_reader_tags = array_column($node->get('field_reader')->getValue(), 'target_id');
    $other_readers = array_diff($node_reader_tags, $user_reader_tags);

    return !empty($other_readers);
  }

}
