<?php

namespace Drupal\aa_gin\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Queue worker for the queue_cancel_account_delete_legacy_works.
 *
 * Marks legacy works for deletion.
 *
 * @QueueWorker(
 *   id = "queue_cancel_account_delete_legacy_works",
 *   title = @Translation("Queue worker that marks legacy works for deletion after an account is deleted."),
 *   cron = {"time" = 60}
 * )
 */
class MarkLegacyWorksForDeletionQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {
  use StringTranslationTrait;

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
    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->entityTypeManager->getStorage('node')->load($data);
    $node->setUnpublished();
    $node->set('field_to_delete', TRUE);
    $node->save();

    $this->logger->info($this->t('Marked legacy work with id @id for deletion', ['@id' => $data]));
  }

}
