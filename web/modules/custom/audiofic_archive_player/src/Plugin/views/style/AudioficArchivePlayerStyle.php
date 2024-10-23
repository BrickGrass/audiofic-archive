<?php

namespace Drupal\audiofic_archive_player\Plugin\views\style;

use Drupal\core\form\FormStateInterface;
use Drupal\views\Plugin\views\style\StylePluginBase;

/**
 * Style plugin to render the mp3 files attached to a work or list of works as a playlist.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "audiofic_archive_player",
 *   title = @Translation("Audiofic Archive Player"),
 *   help = @Translation("Render all of the mp3 files attached to a work (or list of works) as a playlist."),
 *   theme = "views_view_audiofic_archive_player",
 *   display_types = { "normal" }
 * )
 */
class AudioficArchivePlayerStyle extends StylePluginBase {

  /**
   * Set default options.
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['is_series'] = ['default' => FALSE];
    $options['render_mode'] = ['default' => 0];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['is_series'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Render as Series'),
      '#default_value' => (isset($this->options['is_series'])) ? $this->options['is_series'] : FALSE,
      '#description' => $this->t('Playlist entries prefixed with the work title in order to differentiate them.'),
    ];

    $form['render_mode'] = [
      '#type' => 'radios',
      '#title' => $this->t("Render Mode"),
      '#default_value' => (isset($this->options['render_mode'])) ? $this->options['render_mode'] : 0,
      '#description' => $this->t("By default both a playlist + downloads below it are rendered. You can only render one or the other of these sections using this option."),
      '#options' => [
        0 => $this->t('Render Both'),
        1 => $this->t('Render Only Playlist'),
        2 => $this->t('Render Only Downloads'),
      ],
    ];
  }

}
