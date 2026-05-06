<?php

namespace Drupal\aa_utils\Plugin\views\style;

use Drupal\views\Plugin\views\style\StylePluginBase;

/**
 * Style plugin to render fields in a list without wrappers.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "audiofic_archive_list",
 *   title = @Translation("Audiofic Archive List"),
 *   help = @Translation("Renders fields as a list without wrappers around the induvidual fields."),
 *   theme = "views_view_audiofic_archive_list",
 *   display_types = { "normal" }
 * )
 */
class AudioficArchiveListStyle extends StylePluginBase {

}
