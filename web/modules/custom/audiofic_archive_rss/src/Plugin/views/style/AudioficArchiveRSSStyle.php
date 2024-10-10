<?php

namespace Drupal\audiofic_archive_rss\Plugin\views\style;

use Drupal\core\form\FormStateInterface;
use Drupal\views\Plugin\views\style\Rss;

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
class AudioficArchiveRSSStyle extends Rss {

  /**
   * Set default options.
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['is_contextual'] = ['default' => FALSE];
    $options['creator_truncation'] = ['default' => 3];
    $options['override_media_pubdate'] = ['default' => FALSE];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('The title of this RSS feed'),
      '#description' => $this->t('Tokens are available in this field. Hint: this field trims all excess whitespace, so add possibly empty tokens such that only whitespace separates them from the other values.'),
      '#default_value' => (isset($this->options['title'])) ? $this->options['title'] : '',
      '#weight' => 1,
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('The description of this RSS feed'),
      '#description' => $this->t('Tokens are available in this field Hint: this field trims all excess whitespace, so add possibly empty tokens such that only whitespace separates them from the other values.'),
      '#default_value' => (isset($this->options['description'])) ? $this->options['description'] : '',
      '#weight' => 2,
    ];

    $form['link'] = [
      '#type' => 'textfield',
      '#title' => $this->t('The link of this RSS feed'),
      '#description' => $this->t('Provide the link in the format "search/works". Tokens are available in this field'),
      '#default_value' => (isset($this->options['link'])) ? $this->options['link'] : '',
      '#weight' => 3,
    ];

    $form['cover_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('The url of the image to use as a cover'),
      '#description' => $this->t('Provide the link in the format "sites/default/files/2024-04/image.png". If there is a contextually filtered node provided, it\'s image will replace this one.'),
      '#default_value' => (isset($this->options['cover_url'])) ? $this->options['cover_url'] : '',
      '#weight' => 3,
    ];

    $form['creator_truncation'] = [
      '#type' => 'number',
      '#title' => $this->t('Author/Reader truncation value'),
      '#description' => $this->t('How many authors or readers to list before truncating the list. For a work with the author "Sally" and the readers "Darren", "Sophie" and "Jess", a truncation value of 2 would result in "Read by: Darren, Sophie and more | Written by Sally".'),
      '#step' => 1,
      '#min' => 1,
      '#default_value' => (isset($this->options['creator_truncation'])) ? $this->options['creator_truncation'] : 3,
      '#weight' => 4,
    ];

    $form['is_contextual'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use contextual filters to fill RSS feed metadata'),
      '#description' => $this->t('The data of the work or collection that is contextually filtered will be used to decide the metadata of the overall RSS feed. Eg: The title, description, cover image, etc.'),
      '#default_value' => (isset($this->options['is_contextual'])) ? $this->options['is_contextual'] : FALSE,
      '#weight' => 5,
    ];

    $form['override_media_pubdate'] = [
      '#type' => 'checkbox',
      '#title' => $this->t("Override Media Item Publishing Date"),
      '#description' => $this->t("By default each feed item has the publishing date of the media item it refers to. This setting overrides those dates with dates derived from the publishing date of the work they are attached to, so that it is ensured that they are in correct chapter order."),
      '#default_value' => (isset($this->options['override_media_pubdate'])) ? $this->options['override_media_pubdate'] : FALSE,
      '#states' => [
        'visible' => [':input[name="style_options[is_contextual]"]' => ['checked' => TRUE]],
      ],
      '#weight' => 6,
    ];

    $form['token_tree'] = [
      '#type' => 'item',
      '#theme' => 'token_tree_link',
      '#token_types' => ['contextual-filter-node', 'contextual-filter-term', 'exposed-filters'],
      '#show_restricted' => TRUE,
      '#global_types' => FALSE,
      '#weight' => 7,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();
    // Ensure our feed is indexable, or google podcasts won't accept it!
    $build['#attached']['http_header'][] = ['X-Robots-Tag', 'all'];
    return $build;
  }

}
