<?php

namespace Drupal\aa_nsfw_modal\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a block which asks new users if they wish to see nsfw content.
 */
#[Block(
  id: "aa_nsfw_modal_block",
  admin_label: new TranslatableMarkup("NSFW Consent Modal Block"),
  category: new TranslatableMarkup("Blocks")
)]

class NsfwConsentBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'title' => '',
      'body_text' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Modal Title'),
      '#default_value' => $this->configuration['title'],
    ];

    $form['body_text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Modal Body'),
      '#default_value' => $this->configuration['body_text'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $this->configuration['title'] = $values['title'];
    $this->configuration['body_text'] = $values['body_text'];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#theme' => 'aa_nsfw_modal_block',
      '#nsfw_modal_title' => $this->configuration['title'],
      '#nsfw_modal_body_text' => $this->configuration['body_text'],
      '#attached' => [
        'library' => [
          'aa_nsfw_modal/modal',
        ],
      ],
    ];
  }

}
