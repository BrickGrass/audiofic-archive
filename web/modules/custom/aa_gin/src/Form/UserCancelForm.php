<?php

namespace Drupal\aa_gin\Form;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Custom form to handle cancelling user accounts.
 */
class UserCancelForm extends FormBase {

  /**
   * The user to delete the account of.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * The Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The Queue service.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  public function __construct(EntityTypeManager $entity_type_manager, QueueFactory $queue_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->queueFactory = $queue_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('queue'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    ?UserInterface $user = NULL,
  ) {
    if (empty($user)) {
      throw new \Exception('User not provided');
    }
    $this->user = $user;
    $triggering_elem = $form_state->getTriggeringElement();
    if ($triggering_elem && $triggering_elem['#id'] == 'edit-cancel') {
      $response = new RedirectResponse(Url::fromRoute('entity.user.canonical', ['user' => $user->id()])->toString());
      $response->send();
    }

    $form['tag_note'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Orphaning your attribution tags impacts all three types: reader, author and cover artist tags.'),
    ];

    $form['user_cancel_method'] = [
      '#type' => 'radios',
      '#title' => $this->t('When deleting my account'),
      '#required' => TRUE,
      '#options' => [
        'delete' => $this->t('Delete my account & delete all works & series I own, permanently removing the attached podfic files from the archive. Works that I do not own but am attributed on will have my user tag removed and replaced with the orphan-account tag. This action cannot be undone and <b>will</b> affect works with co-owners.'),
        'orphan' => $this->t('Delete my account but retain all works I own. Any works I am attributed on will have my user tag changed to the anonymous orphan-account. This action cannot be undone.'),
        'abandon' => $this->t('Delete my account but retain all works I own. My attribution on those works will remain unchanged. If I want to recreate my account in the future, I will be able to claim these works again.'),
      ],
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Confirm'),
    ];

    $form['cancel'] = [
      '#type' => 'button',
      '#value' => $this->t('Cancel'),
      // Ensure this button functions, even if there are validation issues.
      '#limit_validation_errors' => [],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $cancel_method = $form_state->getValue('user_cancel_method');
    if ($cancel_method == 'delete') {
      $this->deleteOwnedContent();
    }

    if ($cancel_method == 'delete' || $cancel_method == 'orphan') {
      $this->attributeToOrphanTag();
    }

    $this->makeNodeAuthorAnonymous();
    $form_state->setRedirect('<front>');
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'user_cancel_form';
  }

  /**
   * Queue every work & series this user owns for deletion.
   *
   * Legacy works are unpublished and marked for manual deletion
   * by archivists - since the files are not deletable by drupal.
   */
  private function deleteOwnedContent() {
    $user_tags = array_column($this->user->get('field_reader_name')->getValue(), 'target_id');

    if (empty($user_tags)) {
      return;
    }

    /** @var \Drupal\node\NodeStorage $node_storage */
    $node_storage = $this->entityTypeManager->getStorage('node');

    $works_queue = $this->queueFactory->get('queue_cancel_account_delete_nodes');
    $works_and_series = $node_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', ['work', 'playlist'], 'IN')
      ->condition('field_owner.entity:taxonomy_term.tid', $user_tags, 'IN')
      ->execute();
    foreach ($works_and_series as $nid) {
      $works_queue->createItem($nid);
    }

    $legacy_works_queue = $this->queueFactory->get('queue_cancel_account_delete_legacy_works');
    $legacy_works = $node_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'legacy_work')
      ->condition('field_owner.entity:taxonomy_term.tid', $user_tags, 'IN')
      ->execute();
    foreach ($legacy_works as $nid) {
      $legacy_works_queue->createItem($nid);
    }
  }

  /**
   * Transfer authorship of all nodes created by this user to anonymous.
   */
  private function makeNodeAuthorAnonymous() {
    /** @var \Drupal\node\NodeStorage $node_storage */
    $node_storage = $this->entityTypeManager->getStorage('node');
    $nodes = $node_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('uid', $this->user->id())
      ->execute();

    if (empty($nodes)) {
      $this->user->delete();
      return;
    }

    $batch = new BatchBuilder();
    $batch->setTitle('Running batch process.')
      ->setFinishCallback([self::class, 'batchFinished'])
      ->setInitMessage('Starting')
      ->setProgressMessage('Processing...')
      ->setErrorMessage('An error occured while deleting your account. Please contact an administrator so that we can sort things out for you!');

    foreach (array_chunk($nodes, 10) as $node_chunk) {
      $batch->addOperation([self::class, 'batchProcess'], [$this->user->id(), $node_chunk]);
    }
    batch_set($batch->toArray());
  }

  /**
   * Callback which processes part of the user deletion batch job.
   */
  public static function batchProcess($uid, array $nodes, array &$context) {
    if (!isset($context['sandbox']['progress'])) {
      $context['sandbox']['progress'] = 0;
      $context['results']['uid'] = $uid;
    }

    foreach (Node::loadMultiple($nodes) as $node) {
      $node->setOwnerId(0);
      $node->save();
    }

    $context['results']['progress'] += count($nodes);
  }

  /**
   * Callback that runs after user deletion batch job finishes.
   */
  public static function batchFinished(bool $success, array $results, array $operations, string $elapsed) {
    $messenger = \Drupal::messenger();
    if ($success) {
      $user = User::load($results['uid']);
      if (!empty($user)) {
        $user->delete();
      }

      $messenger->addMessage(t('Ensured @count works were separated from your account before deleting it in @elapsed. If you asked to orphan or delete works there may be some lag time while the system processes each of your works - especially if you asked to delete legacy works since this is a manual process. Please do contact an administrator if the deletion is taking unreasonably long, we are always happy to double check and ensure that everything has been deleted correctly.', [
        '@count' => $results['progress'],
        '@elapsed' => $elapsed,
      ]));
      \Drupal::logger('batch_user_delete')->info('Assigned @count works to the anonymous user before deleting the user with id @uid in @elapsed.', [
        '@count' => $results['progress'],
        '@uid' => $results['uid'],
        '@elapsed' => $elapsed,
      ]);
    } else {
      $messenger->addError(t('An error occured while deleting your account! Please contact an administrator so that we can ensure your account is deleted correctly.'));
    }
  }

  /**
   * Queue all attributions to be converted to the orphan-account tag.
   *
   * After doing this, delete each of the user's attribution
   * tags.
   */
  private function attributeToOrphanTag() {
    $queue = $this->queueFactory->get('queue_cancel_account_orphan_works');

    $reader_tags = array_column($this->user->get('field_reader_name')->getValue(), 'target_id');
    $author_tags = array_column($this->user->get('field_author_name')->getValue(), 'target_id');
    $cover_artist_tags = array_column($this->user->get('field_cover_artist_name')->getValue(), 'target_id');

    foreach ($reader_tags as $tid) {
      $queue->createItem(['tid' => $tid, 'vid' => 'reader']);
    }
    foreach ($author_tags as $tid) {
      $queue->createItem(['tid' => $tid, 'vid' => 'author']);
    }
    foreach ($cover_artist_tags as $tid) {
      $queue->createItem(['tid' => $tid, 'vid' => 'cover_artist']);
    }
  }

}
