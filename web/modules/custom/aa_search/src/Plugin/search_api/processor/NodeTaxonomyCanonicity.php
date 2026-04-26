<?php

namespace Drupal\aa_search\Plugin\search_api\processor;

use Drupal\aa_utils\Service\AudioficTagUtils;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Plugin\PluginFormTrait;
use Drupal\search_api\Plugin\search_api\data_type\value\TextValue;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\LoggerTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Excludes taxonomy terms that are not canon from indexes.
 *
 * Terms are only excluded if they have the concept of canonicity
 * and will be replaced by their canon sibling, if they have one.
 */
#[SearchApiProcessor(
  id: 'node_taxonomy_canonicity',
  label: new TranslatableMarkup('Node Taxonomy canonicity'),
  description: new TranslatableMarkup('Exclude taxonomy terms which have the concept of canonicity & are not canon from being indexed. Ensures that if such a tag has a canon sibling, that term is indexed instead.'),
  stages: [
    'alter_items' => 0,
  ],
)]
class NodeTaxonomyCanonicity extends ProcessorPluginBase implements PluginFormInterface {

  use LoggerTrait;
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
    $processor->setLogger($container->get('logger.channel.search_api'));
    $processor->setTagUtils($container->get('aa_utils.tag_utils'));

    return $processor;
  }

  /**
   * Sets the entity type manager service.
   */
  public function setEntityTypeManager(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
    return $this;
  }

  /**
   * Sets the audiofic tag utils service.
   */
  public function setTagUtils(AudioficTagUtils $tag_utils) {
    $this->tagUtils = $tag_utils;
  }

  /**
   * Find the fields which have canonicity awareness.
   */
  protected function getCanonAwareFields() {
    $field_options = [];

    foreach ($this->index->getFields() as $field_id => $field) {
      if (in_array($field_id, ['field_fandom2', 'field_relationship'])) {
        $field_options[] = $field_id;
      }
    }

    return $field_options;
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

      if ($entity_type_id !== 'node') {
        return FALSE;
      }
      // Works, Legacy works and Series are supported.
      foreach ($datasource->getBundles() as $key => $label) {
        if (in_array($key, ['work', 'legacy_work', 'playlist'])) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['fields' => []];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['#description'] = $this->t('Select the fields for which non-canon tags should be stripped.');

    foreach ($this->getCanonAwareFields() as $field_id) {
      $enabled = !empty($this->configuration['fields'][$field_id]);
      $form['fields'][$field_id]['status'] = [
        '#type' => 'checkbox',
        '#title' => $this->index->getField($field_id)->getLabel(),
        '#default_value' => $enabled,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $fields = [];
    foreach ($form_state->getValue('fields', []) as $field_id => $values) {
      if (!empty($values['status'])) {
        $fields[$field_id] = $field_id;
      }
    }

    $form_state->setValue('fields', $fields);
    if (!$fields) {
      $form_state->setError($form['fields'], $this->t('You need to select at least one field for which non-canonical tags are stripped.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function alterIndexedItems(array &$items) {
    /** @var \Drupal\search_api\Item\ItemInterface $item */
    foreach ($items as $item) {
      foreach ($this->configuration['fields'] as $field_id) {
        $field = $item->getField($field_id);
        if (!$field) {
          continue;
        }

        $this->filterNonCanonTags($field);
      }
    }
  }

  /**
   * Rewrites a field to only contain canon tags.
   */
  protected function filterNonCanonTags(FieldInterface $field) {
    $new_values = [];

    foreach ($field->getValues() as $entity_id) {
      if ($entity_id instanceof TextValue) {
        $entity_id = $entity_id->getOriginalText();
      }
      if (!is_scalar($entity_id)) {
        return;
      }

      /** @var \Drupal\taxonomy\TermInterface $term */
      $term = $this->entityTypeManager->getStorage('taxonomy_term')
        ->load($entity_id);
      if (empty($term)) {
        return;
      }
      if (!$this->tagUtils->isTagCanonicityAware($term)) {
        return;
      }

      if ($term->get('field_canonicity')->value == 'canon') {
        $new_values[] = $entity_id;
        continue;
      }

      $canonical_sibling = array_first(array_column($term->get('field_canon_sibling')->getValue(), 'target_id'));
      if (!empty($canonical_sibling)) {
        $new_values[] = $canonical_sibling;
      }
    }

    $field->setValues($new_values);
  }

}
