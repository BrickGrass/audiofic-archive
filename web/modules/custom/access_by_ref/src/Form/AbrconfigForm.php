<?php

namespace Drupal\access_by_ref\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\PhpStorage\PhpStorageFactory;
use Drupal\field\FieldConfigInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form handler for the Example add and edit forms.
 */
class AbrconfigForm extends EntityForm {

  /**
   * Constructs an ExampleForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entityTypeManager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * Function to get all bundles of the type 'node' so we can pre-fill the available options in the form
   *
   * @param string $type
   *
   * @return array
   */

  function getBundlesList($type = 'node'){
    /** @var  $entityManager \Drupal\Core\Entity\EntityTypeBundleInfoInterface  **/
    $entityManager = \Drupal::service('entity_type.bundle.info');
    $bundles = $entityManager->getBundleInfo($type);
    foreach ($bundles as $key => &$item) {
      $bundles[$key] = $item['label'];
    }
    return $bundles;
  }

  /**
   * Function to get all bundles of the type 'node' so we can pre-fill the available options in the form
   */
  function getReferenceTypesList(){

    return array(
      'user' => 'User referenced',
      'user_mail' => "User's mail",
      'shared' => 'Profile value',
      'inherit' => 'Inherit from parent',
    );

  }

  public function fieldDataFetch($contentType, $bundle = 'node', $property = 'label') {
    /** @var  $entityFieldManager  \Drupal\Core\Entity\EntityFieldManagerInterface  **/
    $entityFieldManager = \Drupal::service('entity_field.manager');
    $fields = [];

    if (!empty($contentType)) {
      $fields = array_filter(
        $entityFieldManager->getFieldDefinitions($bundle, $contentType), function ($field_definition) {
        return $field_definition instanceof FieldConfigInterface;
      }
      );
    }

    switch ($property) {
      case 'label':
        foreach ($fields as $key => &$field) {
          $fields[$key] = $field->label();
        }
        break;

      case 'type':
        foreach ($fields as $key => &$field) {
          $fields[$key] = $field->getType();
        }
        break;

      case 'handler':
        foreach ($fields as $key => &$field) {
          $fields[$key] = $field->getSetting('handler');
        }
        break;

      case 'omni':
        foreach ($fields as $key => &$field) {
          $vals = array(
            'handler' => $field->getSetting('handler'),
            'type' => $field->getType(),
            'label' => $field->label(),
          );
          $fields[$key] = $vals;
        }
        break;

    }

    return $fields;
  }

  function getFieldsList(){
    $fields = array();

    $bundles = $this->getBundlesList('node');

    foreach ($bundles as $key => $label) {
      $fields[$key] = $this->fieldDataFetch($key, 'node', 'label');
      unset($fields[$key]['body']);
    }

    return $fields;
  }

  function getUserFieldsList(){
    $fields = array();

    $fields['User fields'] = $this->fieldDataFetch('user', 'user', 'label');

    return $fields;
  }

  function getRightsTypeList(){
    return array(
      'view' => 'View',
      'update' => "Update",
      'delete' => 'Delete'
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {

    $form = parent::form($form, $form_state);

    /** @var \Drupal\access_by_ref\Entity\Abrconfig $example */
    $example = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $example->label(),
      '#description' => $this->t("Label for the abr config entry."),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $example->id(),
      '#machine_name' => [
        'exists' => [$this, 'exist'],
      ],
      '#disabled' => !$example->isNew(),
    ];

    $form['bundle'] = [
      '#type' => 'select',
      '#empty_option' => $this->t('- Select -'),
      '#options' => $this->getBundlesList('node'),
      '#title' => $this->t('Bundle'),
      '#default_value' => $example->getBundle(),
      '#description' => $this->t("Bundle of the abr config entry."),
      '#required' => TRUE,
    ];

    $form['field'] = [
      '#type' => 'select',
      '#empty_option' => $this->t('- Select -'),
      '#options' => $this->getFieldsList(),
      '#title' => $this->t('Field'),
      '#default_value' => $example->getField(),
      '#description' => $this->t("The field of the bundle."),
      '#required' => TRUE,
    ];

    $form['reference_type'] = [
      '#type' => 'select',
      '#empty_option' => $this->t('- Select -'),
      '#options' => $this->getReferenceTypesList(),
      '#title' => $this->t('Reference type'),
      '#default_value' => $example->getReferenceType(),
      '#description' => $this->t("Reference type of the abr config entry."),
      '#required' => TRUE,
    ];

    $form['extra'] = [
      '#type' => 'select',
      '#empty_option' => $this->t('- Select -'),
      '#options' => $this->getUserFieldsList(),
      '#title' => $this->t('Extra field'),
      '#default_value' => $example->getExtra(),
      '#description' => $this->t("In case of matching a node value with user 'profile value', specify here the user field that has to match."),
      '#required' => FALSE,
    ];

    $form['rights_type'] = [
      '#type' => 'select',
      '#empty_option' => $this->t('- Select -'),
      '#options' => $this->getRightsTypeList(),
      '#title' => $this->t('Rights type field'),
      '#default_value' => $example->getRightsType(),
      '#description' => $this->t("Select against which operation the 'parent' node / element needs to be checked."),
      '#required' => FALSE,
    ];

    $form['rights_read'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Assign read rights?'),
      '#default_value' => $example->getRightsRead(),
      '#description' => $this->t(""),
      '#required' => FALSE,
    ];

    $form['rights_update'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Assign update rights?'),
      '#default_value' => $example->getRightsUpdate(),
      '#description' => $this->t(""),
      '#required' => FALSE,
    ];

    $form['rights_delete'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Assign delete rights?'),
      '#default_value' => $example->getRightsDelete(),
      '#description' => $this->t(""),
      '#required' => FALSE,
    ];

    $form['#attached']['library'][] = 'access_by_ref/configform';

    // You will need additional form elements for your custom properties.
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {

    /** @var \Drupal\access_by_ref\Entity\Abrconfig $example */
    $example = $this->entity;

    $status = $example->save();

    if ($status === SAVED_NEW) {
      $this->messenger()->addMessage($this->t('The %label configuration created.', [
        '%label' => $example->label(),
      ]));
    }
    else {
      $this->messenger()->addMessage($this->t('The %label configuration updated.', [
        '%label' => $example->label(),
      ]));
    }

    // Wipe the Twig PHP Storage cache. ABR needed.
    \Drupal::service('cache.render')->invalidateAll();

    $form_state->setRedirect('entity.abrconfig.collection');
  }

  /**
   * Helper function to check whether an Example configuration entity exists.
   */
  public function exist($id) {
    $entity = $this->entityTypeManager->getStorage('abrconfig')->getQuery()
      ->accessCheck(FALSE)
      ->condition('id', $id)
      ->execute();
    return (bool) $entity;
  }

}
