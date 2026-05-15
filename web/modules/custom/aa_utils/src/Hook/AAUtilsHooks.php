<?php

namespace Drupal\aa_utils\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Class AAUtilsHooks defines hooks for the aa_utils module.
 */
class AAUtilsHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_form_FORM_ID_alter() for contact_message_feedback_form.
   */
  #[Hook('form_contact_message_feedback_form_alter')]
  public function contactFormAlter(&$form, FormStateInterface $form_state, $form_id) {
    $form['about'] = [
      '#type' => 'container',
      '#weight' => -99999,
      '#attributes' => [
        'class' => ['mb-3', 'd-flex', 'flex-row'],
      ],
    ];

    $form['about']['text'] = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => $this->t("Please note that if you haven't had a response in a reasonable amount of time, you can contact the mods at"),
      '#attributes' => [
        'class' => ['pe-1'],
      ],
    ];
    $email_link = Link::fromTextAndUrl($this->t('jinjurlymods@squidge.org'), Url::fromUri('mailto:jinjurlymods@squidge.org'));
    $form['about']['link'] = $email_link->toRenderable();
  }

}
