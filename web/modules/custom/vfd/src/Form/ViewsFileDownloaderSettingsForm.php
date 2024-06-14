<?php

namespace Drupal\vfd\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configuration page for Views file downloader settings.
 */
class ViewsFileDownloaderSettingsForm extends ConfigFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Construct function.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory load.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($config_factory);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'vfd_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'vfd.settings',
    ];
  }

  /**
   * {@inheritdoc}
   *
   * Implements admin settings form.
   *
   * @param array $form
   *   From render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Current state of form.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Fetch configurations if saved.
    $config = $this->config('vfd.settings');

    // Create headers for table.
    $header = [
      $this->t('View'),
      $this->t('Field'),
      $this->t('Field Type'),
      $this->t('Operation'),
    ];

    // Multi value table form.
    $form['vfd_table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#empty' => $this->t('There are no items yet. Add an item.', []),
      '#prefix' => '<div id="vfd-fieldset-wrapper">',
      '#suffix' => '</div>',
    ];

    // Available views.
    $views = $this->entityTypeManager->getStorage('view')->loadMultiple();
    $views_list = [];

    foreach ($views as $view_machine_name => $view) {
      $views_list[$view_machine_name] = $view->get('label');
    }

    // Available fields.
    $fields = $this->entityTypeManager->getStorage('field_storage_config')->loadMultiple();
    $fields_list = [];

    foreach ($fields as $field) {
      $field_name = $field->get('field_name');
      $fields_list[$field_name] = $field_name;
    }

    // Set table values on Add/Remove or on page load.
    $vfd_table = $form_state->get('vfd_table');
    if (empty($vfd_table)) {
      // Set data from configuration on page load.
      // Set empty element if no configurations are set.
      if (!empty($config->get('vfd_table'))) {
        $vfd_table = $config->get('vfd_table');
        $form_state->set('vfd_table', $vfd_table);
      }
      else {
        $vfd_table = [''];
        $form_state->set('vfd_table', $vfd_table);
      }
    }

    // Provide ability to remove first element.
    if (isset($vfd_table['removed']) && $vfd_table['removed']) {
      // Not required if first element is empty.
      reset($vfd_table);
      unset($vfd_table['removed']);
    }

    // Create row for table.
    foreach ($vfd_table as $i => $value) {
      $form['vfd_table'][$i]['view'] = [
        '#type' => 'select',
        '#title' => $this->t('View'),
        '#title_display' => 'invisible',
        '#options' => $views_list,
        '#default_value' => $value['view'] ?? [],
      ];

      $form['vfd_table'][$i]['field'] = [
        '#type' => 'select',
        '#title' => $this->t('Field'),
        '#title_display' => 'invisible',
        '#options' => $fields_list,
        '#default_value' => $value['field'] ?? [],
      ];

      $form['vfd_table'][$i]['field_type'] = [
        '#type' => 'select',
        '#title' => $this->t('Field Type'),
        '#title_display' => 'invisible',
        '#options' => [
          'file' => $this->t('File'),
          'media' => $this->t('Media'),
        ],
        '#default_value' => $value['field_type'] ?? [],
      ];

      $form['vfd_table'][$i]['remove'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove'),
        '#name' => "remove-" . $i,
        '#submit' => ['::removeElement'],
        '#limit_validation_errors' => [],
        '#ajax' => [
          'callback' => '::removeCallback',
          'wrapper' => 'vfd-fieldset-wrapper',
        ],
        '#index_position' => $i,
      ];

    }

    $form['add_name'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add one more'),
      '#submit' => ['::addOne'],
      '#ajax' => [
        'callback' => '::addMoreCallback',
        'wrapper' => 'vfd-fieldset-wrapper',
      ],
    ];

    $form_state->setCached(FALSE);

    return parent::buildForm($form, $form_state);
  }

  /**
   * Callback for ajax-enabled add buttons.
   *
   * Selects and returns the fieldset with the names in it.
   */
  public function addMoreCallback(array &$form, FormStateInterface $form_state) {
    return $form['vfd_table'];
  }

  /**
   * Submit handler for the "Add one more" button.
   *
   * Add a blank element in table and causes a rebuild.
   */
  public function addOne(array &$form, FormStateInterface $form_state) {
    $vfd_table = $form_state->get('vfd_table');
    array_push($vfd_table, "");
    $form_state->set('vfd_table', $vfd_table);
    $form_state->setRebuild();
  }

  /**
   * Callback for ajax-enabled remove buttons.
   *
   * Selects and returns the fieldset with the names in it.
   */
  public function removeCallback(array &$form, FormStateInterface $form_state) {
    return $form['vfd_table'];
  }

  /**
   * Submit handler for the "Remove" button(s).
   *
   * Remove the element from table and causes a form rebuild.
   */
  public function removeElement(array &$form, FormStateInterface $form_state) {
    // Get table.
    $vfd_table = $form_state->get('vfd_table');
    // Get element to remove.
    $remove = key($form_state->getValue('vfd_table'));
    // Remove element.
    unset($vfd_table[$remove]);
    // Set an empty element if no elements are left.
    if (empty($vfd_table)) {
      array_push($vfd_table, "");
    }
    // Set removed flag for removed item.
    $vfd_table['removed'] = TRUE;
    $form_state->set('vfd_table', $vfd_table);
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Retrieve the configuration.
    $this->config('vfd.settings')
    // Set the submitted configuration setting.
      ->set('vfd_table', $form_state->getValue('vfd_table'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
