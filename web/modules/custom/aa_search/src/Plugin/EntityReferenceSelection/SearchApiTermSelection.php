<?php

namespace Drupal\aa_search\Plugin\EntityReferenceSelection;

use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigValueException;
use Drupal\Core\Entity\Attribute\EntityReferenceSelection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\ParseMode\ParseModePluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Allows selecting and searching for taxonomy terms via a search index.
 */
#[EntityReferenceSelection(
  id: "search_index:taxonomy_term",
  label: new TranslatableMarkup("Search API Taxonomy Term selection"),
  entity_types: ["taxonomy_term"],
  group: "default",
  weight: 1
)]
class SearchApiTermSelection extends DefaultSelection {

  /**
   * The parse mode Plugin Manager.
   *
   * @var \Drupal\search_api\ParseMode\ParseModePluginManager
   */
  protected $parseModePluginManager;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler, AccountInterface $current_user, EntityFieldManagerInterface $entity_field_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info, EntityRepositoryInterface $entity_repository, ParseModePluginManager $parse_mode_plugin_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $module_handler, $current_user, $entity_field_manager, $entity_type_bundle_info, $entity_repository);

    $this->parseModePluginManager = $parse_mode_plugin_manager;
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
      $container->get('module_handler'),
      $container->get('current_user'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity.repository'),
      $container->get('plugin.manager.search_api.parse_mode')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'search_index' => NULL,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    // Sorting is not possible for taxonomy terms because we use
    // \Drupal\taxonomy\TermStorageInterface::loadTree() to retrieve matches.
    $form['sort']['#access'] = FALSE;

    $form['search_index'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search API Taxonomy Index'),
      '#required' => TRUE,
    ];

    return $form;

  }

  /**
   * {@inheritdoc}
   */
  public function getReferenceableEntities($match = NULL, $match_operator = 'CONTAINS', $limit = 0) {
    $target_type = $this->getConfiguration()['target_type'];

    $query = $this->buildSearchQuery($match)
      ->range(0, $limit == 0 ? NULL : $limit);

    $result = $query->execute();
    if (empty($result->getResultCount())) {
      return [];
    }

    $term_ids = [];
    foreach ($result->getResultItems() as $item) {
      $term_ids[] = array_first($item->getField('tid')->getValues());
    }

    $options = [];
    $entities = $this->entityTypeManager->getStorage($target_type)->loadMultiple($term_ids);
    foreach ($entities as $entity_id => $entity) {
      $bundle = $entity->bundle();
      $options[$bundle][$entity_id] = Html::escape($this->entityRepository->getTranslationFromContext($entity)->label() ?? '');
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function countReferenceableEntities($match = NULL, $match_operator = 'CONTAINS') {
    $query = $this->buildSearchQuery($match);
    return $query->execute()->getResultCount();
  }

  /**
   * Builds a Search API query to get taxonomy terms.
   *
   * @param string|null $match
   *   (Optional) Text to match the label against. Defaults to NULL.
   *
   * @return \Drupal\search_api\Query\QueryInterface
   *   The Query object with the basic conditions applied.
   */
  protected function buildSearchQuery($match = NULL) {
    $configuration = $this->getConfiguration();
    $target_bundles = $configuration['target_bundles'];
    $search_index = $configuration['search_index'];

    if (empty($search_index)) {
      throw new \InvalidArgumentException('The configuration value search_index cannot be NULL/empty.');
    }

    $index = Index::load($search_index);
    $query = $index->query();

    // TODO: Could consider terms parse mode plugin?
    /** @var \Drupal\search_api\ParseMode\ParseModeInterface $parse_mode */
    $parse_mode = $this->parseModePluginManager->createInstance('direct');
    $parse_mode->setConjunction('OR');
    $query->setParseMode($parse_mode);

    $query->keys($match);
    if (is_array($target_bundles)) {
      $query->addCondition('vid', $target_bundles, 'IN');
    }

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function createNewEntity($entity_type_id, $bundle, $label, $uid) {
    $term = parent::createNewEntity($entity_type_id, $bundle, $label, $uid);

    // In order to create a referenceable term, it needs to published.
    /** @var \Drupal\taxonomy\TermInterface $term */
    $term->setPublished();

    return $term;
  }

  /**
   * {@inheritdoc}
   */
  public function validateReferenceableNewEntities(array $entities) {
    $entities = parent::validateReferenceableNewEntities($entities);
    // Mirror the conditions checked in buildEntityQuery().
    $entities = array_filter($entities, function ($term) {
      /** @var \Drupal\taxonomy\TermInterface $term */
      return $term->isPublished();
    });
    return $entities;
  }

}
