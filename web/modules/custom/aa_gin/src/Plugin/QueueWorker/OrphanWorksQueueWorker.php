<?php

namespace Drupal\aa_gin\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Queue worker for the queue_cancel_account_orphan_works.
 *
 * Replaces attribution tags with the orphan_account tag & deletes them.
 *
 * @QueueWorker(
 *   id = "queue_cancel_account_orphan_works",
 *   title = @Translation("Queue worker that replaces attribution tags with the orphan_account tag after an account is deleted."),
 *   cron = {"time" = 60}
 * )
 */
class OrphanWorksQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {
  use StringTranslationTrait;

  protected const ORPHAN_ACCOUNT_ID = [
    'reader' => 18383,
    'author' => 9287,
    // TODO: Will need to be updated on site launch!
    'cover_artist' => 22793,
  ];

  /**
   * Logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerChannelInterface $logger, EntityTypeManager $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->logger = $logger;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.channel.delete_account_audit_log'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $tid = $data['tid'];
    $vid = $data['vid'];

    /** @var \Drupal\taxonomy\TermStorage $term_storage */
    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $orphan_account = $term_storage->load($this::ORPHAN_ACCOUNT_ID[$vid]);

    /** @var \Drupal\node\NodeStorage $node_storage */
    $node_storage = $this->entityTypeManager->getStorage('node');
    $query = $node_storage->getQuery()->accessCheck(FALSE);
    $group = $query->orConditionGroup();
    switch ($vid) {
      case 'reader':
        $group->condition('field_owner.entity:taxonomy_term.tid', $tid);
        $group->condition('field_reader.entity:taxonomy_term.tid', $tid);
        break;

      case 'author':
        $group->condition('field_author.entity:taxonomy_term.tid', $tid);
        break;

      case 'cover_artist':
        $group->condition('field_cover_artist.entity:taxonomy_term.tid', $tid);
        break;
    }
    $nodes = $query->condition($group)->execute();

    /** @var \Drupal\node\NodeInterface $node */
    foreach ($node_storage->loadMultiple($nodes) as $node) {
      switch ($vid) {
        case 'reader':
          $this->removeFieldAttribution($tid, $node, 'field_owner', $orphan_account);
          $this->removeFieldAttribution($tid, $node, 'field_reader', $orphan_account);
          break;

        case 'author':
          $this->removeFieldAttribution($tid, $node, 'field_author', $orphan_account);
          break;

        case 'cover_artist':
          $this->removeFieldAttribution($tid, $node, 'field_cover_artist', $orphan_account);
          break;
      }
      $node->save();
      $this->logger->info($this->t('Removed attribution tag with tid @tid from node with nid @nid',
                                   ['@tid' => $tid, '@nid' => $node->id()]));
    }

    $term = $term_storage->load($tid);
    $term->delete();
    $this->logger->info($this->t('Deleted attribution tag with tid @tid', ['@tid' => $tid]));
  }

  /**
   * Replaces any user attributions with the orphan_account.
   *
   * If the user is not attributed in this field, does nothing.
   */
  private function removeFieldAttribution($tid, NodeInterface $node, string $field_name, TermInterface $orphan_account) {
    $current_tags = $node->get($field_name)->referencedEntities();
    $updated_tags = [];
    $add_orphan_account = FALSE;

    foreach ($current_tags as $tag) {
      if ($tag->id() == $tid) {
        $add_orphan_account = TRUE;
        continue;
      }
      $updated_tags[] = $tag;
    }
    if (!$add_orphan_account) {
      return;
    }

    $updated_tags[] = $orphan_account;
    $node->set($field_name, $updated_tags);
  }

}
