<?php

namespace Drupal\aa_utils\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Displays Settings, Delete etc links when a user has access to edit a user.
 *
 * @Block(
 *   id = "user_contextual_menu",
 *   admin_label = @Translation("User Contextual Menu Block"),
 *   category = @Translation("Menus"),
 *   context_definitions = {
 *     "user" = @ContextDefinition("entity:user", label = @Translation("User"))
 *   }
 * )
 */
class UserContextualMenuBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs a UserContextualMenuBlock object.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityTypeManagerInterface $entity_type_manager,
    protected AccountInterface $current_user,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
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
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $user = $this->getContextValue('user');
    $uid = $user->id();
    $current_user = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());

    return [
      '#theme' => 'user-contextual-menu',
      '#uid' => $uid,
      '#has_edit_access' => $user->access('update', $current_user),
      '#cache' => [
        'contexts' => ['user'],
        'tags' => ["user:{$uid}"],
      ],
    ];
  }

}
