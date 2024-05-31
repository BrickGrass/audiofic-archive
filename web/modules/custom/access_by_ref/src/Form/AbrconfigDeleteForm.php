<?php
namespace Drupal\access_by_ref\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\PhpStorage\PhpStorageFactory;
use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;

/**
 * Builds the form to delete an Example.
 */

class AbrconfigDeleteForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete %name?', array('%name' => $this->entity->label()));
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.abrconfig.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->entity->delete();
    $this->messenger()->addMessage($this->t('Entity %label has been deleted.', array('%label' => $this->entity->label())));

    // Wipe the Twig PHP Storage cache. ABR needed.
    \Drupal::service('cache.render')->invalidateAll();

    $form_state->setRedirectUrl($this->getCancelUrl());
  }
}
