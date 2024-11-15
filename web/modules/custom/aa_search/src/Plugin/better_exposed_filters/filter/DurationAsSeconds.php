<?php

namespace Drupal\aa_search\Plugin\better_exposed_filters\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\better_exposed_filters\Plugin\better_exposed_filters\filter\FilterWidgetBase;

/**
 * Duration filter for BEF.
 *
 * @BetterExposedFiltersFilterWidget(
 *  id = "bef_duration",
 *  label = @Translation("Duration"),
 * )
 */
class DurationAsSeconds extends FilterWidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(mixed $handler = NULL, array $options = []): bool {
    /** @var \Drupal\views\Plugin\views\filter\FilterPluginBase $handler */
    return is_a($handler, 'Drupal\views\Plugin\views\filter\NumericFilter');
  }

  /**
   * {@inheritdoc}
   */
  public function exposedFormAlter(array &$form, FormStateInterface $form_state): void {
    parent::exposedFormAlter($form, $form_state);

    $field_id = $this->getExposedFilterFieldId();
    if (!isset($form[$field_id])) {
      return;
    }

    $form[$field_id]['#type'] = 'number';
  }

}
