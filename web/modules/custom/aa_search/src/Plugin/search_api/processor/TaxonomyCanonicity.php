<?php

namespace Drupal\aa_search\Plugin\search_api\processor;

use Drupal\aa_utils\Service\AudioficTagUtils;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Plugin\PluginFormTrait;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Excludes taxonomy terms which are not canon from being indexed.
 *
 * Terms are only excluded if they have the concept of canonicity.
 */
#[SearchApiProcessor(
  id: 'taxonomy_canonicity',
  label: new TranslatableMarkup('Taxonomy canonicity'),
  description: new TranslatableMarkup('Exclude taxonomy terms which are not canon from being indexed. If a tag is not canon aware it is not filtered.'),
  stages: [
    'alter_items' => 0,
  ],
)]
class TaxonomyCanonicity extends ProcessorPluginBase {

  use PluginFormTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The audiofic tag utils service.
   *
   * @var \Drupal\aa_utils\Service\AudioficTagUtils
   */
  protected $tagUtils;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $processor */
    $processor = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    $processor->setEntityTypeManager($container->get('entity_type.manager'));
    $processor->setTagUtils($container->get('aa_utils.tag_utils'));

    return $processor;
  }

  /**
   * Sets the entity type manager service.
   */
  public function setEntityTypeManager(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Sets the audiofic tag utils service.
   */
  public function setTagUtils(AudioficTagUtils $tag_utils) {
    $this->tagUtils = $tag_utils;
  }

  /**
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index) {
    foreach ($index->getDatasources() as $datasource) {
      $entity_type_id = $datasource->getEntityTypeId();
      if (!$entity_type_id) {
        continue;
      }

      if ($entity_type_id == 'taxonomy_term') {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function alterIndexedItems(array &$items) {
    /** @var \Drupal\search_api\Item\ItemInterface $item */
    foreach ($items as $item_id => $item) {
      $object = $item->getOriginalObject()->getValue();
      if (!($object instanceof TermInterface)) {
        continue;
      }

      if (!$this->tagUtils->isTagCanonicityAware($object)) {
        continue;
      }

      if ($object->get('field_canonicity')->value != 'canon') {
        unset($items[$item_id]);
      }
    }
  }

}
