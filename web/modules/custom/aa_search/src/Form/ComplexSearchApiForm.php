<?php

namespace Drupal\aa_search\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Builds the search form for a complex search block.
 */
class ComplexSearchApiForm extends FormBase {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs a new ComplexSearchApiForm.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(ConfigFactoryInterface $config_factory, RendererInterface $renderer) {
    $this->configFactory = $config_factory;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'complex_search_api_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $action_url = NULL, $input_name = 'keys') {
    if (!$action_url) {
      $form['message'] = [
        '#markup' => $this->t("Search is currently disabled"),
      ];
      return $form;
    }

    $form['#action'] = Url::fromUri("internal:{$action_url}")->toString();
    $form['#method'] = 'get';

    $form[$input_name] = [
      '#type' => 'search',
      '#title' => $this->t('Keyword Search'),
      '#size' => 15,
      '#default_value' => '',
      '#attributes' => ['title' => $this->t('Enter the terms you wish to search for.')],
      '#search_api_blocks' => TRUE,
    ];

    $form['sort_bef_combine'] = [
      '#type' => 'select',
      '#title' => $this->t('Sort by'),
      '#default_value' => 'changed_DESC',
      '#options' => [
        'changed_DESC' => $this->t('Date Updated (New to Old)'),
        'changed_ASC' => $this->t('Date Updated (Old to New)'),
        'created_DESC' => $this->t('Date Posted (New to Old)'),
        'created_ASC' => $this->t('Date Posted (Old to New)'),
        'title_DESC' => $this->t('Title (Z to A)'),
        'title_ASC' => $this->t('Title (Z to A)'),
        'field_duration_seconds_DESC' => $this->t('Duration (Long to Short)'),
        'field_duration_seconds_ASC' => $this->t('Duration (Short to Long)'),
        'field_reader_name_DESC' => $this->t('Reader (Z to A)'),
        'field_reader_name_ASC' => $this->t('Reader (A to Z)'),
        'field_author_name_DESC' => $this->t('Author (Z to A)'),
        'field_author_name_ASC' => $this->t('Author (A to Z)'),
        'search_api_relevance_DESC' => $this->t('Relevance'),
      ],
    ];

    $form['fandom'] = [
      '#type' => 'entity_autocomplete_aa',
      '#title' => $this->t('Fandom'),
      '#target_type' => 'taxonomy_term',
      // Allow multiple selection.
      '#tags' => TRUE,
      '#selection_settings' => [
        'search_index' => 'canon_taxonomy_terms',
        'target_bundles' => ['fandom'],
      ],
    ];

    $form['relationship'] = [
      '#type' => 'entity_autocomplete_aa',
      '#title' => $this->t('Relationship'),
      '#target_type' => 'taxonomy_term',
      // Allow multiple selection.
      '#tags' => TRUE,
      '#selection_settings' => [
        'search_index' => 'canon_taxonomy_terms',
        'target_bundles' => ['relationship'],
      ],
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
      // Prevent op from showing up in the query string.
      '#name' => '',
      '#search_api_blocks' => TRUE,
    ];

    $form['#attached']['library'][] = 'aa_search/homepage-search-sidebar';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // This form submits to the search page, so processing happens there.
  }

}
