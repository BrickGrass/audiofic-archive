<?php

namespace Drupal\audiofic_archive_rss\Plugin\views\style;

use Drupal\core\form\FormStateInterface;
use Drupal\views\Plugin\views\style\StylePluginBase;

/**
 * Style plugin to render Podcast RSS feeds from lists of works.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "audiofic_archive_rss",
 *   title = @Translation("Audiofic Archive Podcast RSS Feed"),
 *   help = @Translation("Creates a podcast RSS feed for all of the works in a view. Each streaming file attached to a work is given it's own separate entry."),
 *   theme = "views_view_audiofic_archive_rss",
 *   display_types = { "feed" }
 * )
 */
class AudioficArchiveRSSStyle extends StylePluginBase {

  /**
   * Set default options.
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['is_contextual'] = ['default' => FALSE];
    $options['creator_truncation'] = ['default' => 3];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['is_contextual'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use contextual filters to fill RSS feed metadata'),
      '#description' => $this->t('The data of the work or collection that is contextually filtered will be used to decide the metadata of the overall RSS feed. Eg: The title, description, cover image, etc.'),
      '#default_value' => (isset($this->options['is_contextual'])) ? $this->options['is_contextual'] : FALSE,
    ];

    $form['contextual_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description format string for contextual RSS feed'),
      '#description' => $this->t('The format string to use for the rss feed\'s description if contextual filters are being used to fill rss feed metadata. The replacement strings available for this field are %1$s for the title and %2$s for the aggreggated tag data.'),
      '#default_value' => (isset($this->options['contextual_description'])) ? $this->options['contextual_description'] : '',
      '#states' => [
        'visible' => [':input[name="style_options[is_contextual]"]' => ['checked' => TRUE]],
      ],
    ];

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('The title of this RSS feed'),
      '#default_value' => (isset($this->options['title'])) ? $this->options['title'] : '',
      '#states' => [
        'visible' => [':input[name="style_options[is_contextual]"]' => ['checked' => FALSE]],
      ],
    ];

    $form['description'] = [
      '#type' => 'textfield',
      '#title' => $this->t('The description of this RSS feed'),
      '#default_value' => (isset($this->options['description'])) ? $this->options['description'] : '',
      '#states' => [
        'visible' => [':input[name="style_options[is_contextual]"]' => ['checked' => FALSE]],
      ],
    ];

    $form['creator_truncation'] = [
      '#type' => 'number',
      '#title' => $this->t('Author/Reader truncation value'),
      '#description' => $this->t('How many authors or readers to list before truncating the list. For a work with the author "Sally" and the readers "Darren", "Sophie" and "Jess", a truncation value of 2 would result in "Read by: Darren, Sophie and more | Written by Sally".'),
      '#step' => 1,
      '#min' => 1,
      '#default_value' => (isset($this->options['creator_truncation'])) ? $this->options['creator_truncation'] : 3,
    ];
  }

}
