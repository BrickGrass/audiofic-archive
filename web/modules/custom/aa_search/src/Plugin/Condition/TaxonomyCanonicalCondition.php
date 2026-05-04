<?php

namespace Drupal\aa_search\Plugin\Condition;

use Drupal\aa_utils\Service\AudioficTagUtils;
use Drupal\Core\Condition\Attribute\Condition;
use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the Taxonomy is Canonical condition.
 *
 * Used to conditionally render blocks (or not) based on whether
 * the page they will be rendered on contains a canonical taxonomy
 * term or not.
 */
#[Condition(
  id: 'taxonomy_canonical_condition',
  label: new TranslatableMarkup('Taxonomy term canonical condition'),
  context_definitions: [
    'taxonomy_term' => new EntityContextDefinition(
      data_type: 'entity:taxonomy_term',
      label: new TranslatableMarkup('Taxonomy Term'),
      required: FALSE,
    ),
  ]
)]
class TaxonomyCanonicalCondition extends ConditionPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The Tag Utils Service.
   *
   * @var \Drupal\aa_utils\Service\AudioficTagUtils
   */
  protected $tagUtils;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('aa_utils.tag_utils'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AudioficTagUtils $tag_utils) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->tagUtils = $tag_utils;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['disable_not_canonical'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide when taxonomy term is not canonical'),
      '#default_value' => $this->configuration['disable_not_canonical'],
      '#description' => $this->t('Hide this block when on a non-canonical taxonomy term page'),
    ];

    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['disable_not_canonical'] = $form_state->getValue('disable_not_canonical');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['disable_not_canonical' => 0] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   *
   * TODO: I'm not taking into account the negation field right now.
   * If I ever end up wanting to use this more generally, probably will want
   * to do that!
   */
  public function evaluate() {
    $term = $this->getContextValue('taxonomy_term');
    if (!$term) {
      return TRUE;
    }
    if (!$this->configuration['disable_not_canonical']) {
      return TRUE;
    }
    if (!$this->tagUtils->isTagCanonicityAware($term)) {
      return TRUE;
    }

    // Hide if not canonical.
    return $term->get('field_canonicity')->value == 'canon';
  }

  /**
   * {@inheritdoc}
   */
  public function summary() {
    $status = $this->configuration['disable_not_canonical'] ? $this->t('enabled') : $this->t('disabled');
    return $this->t(
      'Hide when taxonomy term is not canonical: @status.',
      ['@status' => $status],
    );
  }

}
