<?php

namespace Drupal\aa_search\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\aa_search\Form\SimpleSearchApiForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a simple search Block with a fulltext searchbox.
 */
#[Block(
  id: "simple_search_block",
  admin_label: new TranslatableMarkup("Simple Search Block"),
  category: new TranslatableMarkup("Forms")
)]

class SimpleSearchApiBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Constructs a new SearchLocalTask.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    FormBuilderInterface $form_builder,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $url = $this->configuration['action_url'] ?? NULL;
    $input_name = $this->configuration['input_name'] ?? '';

    return $this->formBuilder->getForm(
      SimpleSearchApiForm::class,
      $url,
      $input_name
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'action_url' => '',
      'input_name' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['action_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search page'),
      '#required' => TRUE,
      '#description' => $this->t(
        'The search page that the form submits to (e.g. /search).'
      ),
      '#default_value' => $this->configuration['action_url'],
    ];

    $form['input_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Input name'),
      '#required' => FALSE,
      '#placeholder' => 'keys',
      '#description' => $this->t('The name of the search input. This should be the name of the exposed filter'),
      '#default_value' => $this->configuration['input_name'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['action_url'] = $form_state->getValue('action_url');
    $this->configuration['input_name'] = $form_state->getValue('input_name');
  }

  /**
   * {@inheritdoc}
   */
  public function blockValidate($form, FormStateInterface $form_state) {
    if (($value = $form_state->getValue('action_url'))
      && strpos($value, '/') !== 0
    ) {
      $form_state->setErrorByName(
        'action_url', $this->t(
          "The path '%path' has to start with a slash.", [
            '%path' => $value,
          ])
      );
    }
  }

}
