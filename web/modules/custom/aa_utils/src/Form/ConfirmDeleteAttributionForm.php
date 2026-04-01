<?php

namespace Drupal\aa_utils\Form;

use Drupal\aa_utils\Service\AudioficUtils;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form which allows a user to remove their attribution after confirmation.
 *
 * An attribution is one of the user's reader tags being listed in either the
 * readers/owners list of a work.
 */
class ConfirmDeleteAttributionForm extends ConfirmFormBase {

  /**
   * The Node to remove attribution from.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * The User who wants their reader tag removed.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  public function __construct(
    protected AudioficUtils $utils,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('aa_utils.utils'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    ?NodeInterface $node = NULL,
    ?UserInterface $user = NULL,
  ) {
    $this->node = $node;
    $this->user = $user;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $user_tags = array_column($this->user->get('field_reader_name')->getValue(), 'target_id');
    $this->removeUsersTags($user_tags, 'field_reader');
    $this->node->save();

    $form_state->setRedirect('entity.node.canonical', ['node' => $this->node->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() : string {
    return "confirm_delete_attribution_form";
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return URL::fromRoute('entity.node.canonical', ['node' => $this->node->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t(
      'Do you want to delete your reader attribution from %node?',
      ['%node' => $this->node->getTitle()]
    );
  }

  /**
   * Determines whether a user can access this form.
   */
  public function access(AccountInterface $account, NodeInterface $node): AccessResultInterface {
    if (!$user = User::load($account->id())) {
      return AccessResult::forbidden();
    }

    foreach (Role::loadMultiple($user->getRoles()) as $role) {
      if ($role->isAdmin()) {
        return AccessResult::allowed();
      }
    }

    return AccessResult::allowedIf($this->utils->isUserAttributed($user, $node));
  }

  /**
   * Remove all tags with ids in users_tags from the node field specified.
   */
  private function removeUsersTags(array $users_tags, string $field_name) {
    $current_tags = $this->node->get($field_name)->referencedEntities();
    $updated_tags = [];

    foreach ($current_tags as $tag) {
      if (in_array($tag->id(), $users_tags)) {
        continue;
      }

      $updated_tags[] = $tag;
    }

    if (empty($updated_tags)) {
      $this->messenger()->addError($this->t('No other users are readers on this work - you cannot remove your attribution! Please contract an administrator if this is in error.'));
      return;
    }

    $this->node->set($field_name, $updated_tags);
  }

}
