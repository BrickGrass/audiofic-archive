<?php

namespace Drupal\aa_gin\Hook;

use Drupal\aa_utils\Service\AudioficUtils;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\taxonomy\TermInterface;
use Drupal\user\Entity\User;
use Drupal\node\Entity\Node;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use function PHPUnit\Framework\isInstanceOf;

class AAGinFormHooks {
  use StringTranslationTrait;

  public function __construct(
    protected AccountInterface $current_user,
    protected AudioficUtils $utils,
  ) {}

  /**
   * Implements hook_form_alter().
   */
  #[Hook('form_alter')]
  public function formAlter(&$form, FormStateInterface $form_state, string $form_id) {
    $this->alterTagify($form);

    if (in_array($form_id, ['node_work_form', 'node_work_edit_form'])) {
      $this->insertSeriesCreationWidget($form);
    }

    if (in_array($form_id, ['node_work_form', 'node_playlist_form', 'node_legacy_work_form'])) {
      $this->prefillReaderOwnerFields($form);
    }
  }

  /**
   * Implements hook_form_FORM_ID_alter().
   *
   * Implemented for all user-creatable tag types. Protects the creation
   * form so that non-admin users cannot edit protected fields.
   */
  #[Hook('form_taxonomy_term_fandom_form_alter')]
  #[Hook('form_taxonomy_term_relationship_form_alter')]
  #[Hook('form_taxonomy_term_author_form_alter')]
  #[Hook('form_taxonomy_term_reader_form_alter')]
  #[Hook('form_taxonomy_term_cover_artist_form_alter')]
  public function formTaxonomyAlter(&$form, FormStateInterface $form_state, $form_id) {
    $user = User::load($this->current_user->id());
    if ($user && $user->hasRole('administrator')) {
      return;
    }

    $form['status']['#access'] = FALSE;
    $form['relations']['#access'] = FALSE;
    $form['revision_information']['#access'] = FALSE;

    if (isset($form['field_canonicity'])) {
      $form['field_canonicity']['#access'] = FALSE;
    }
    if (isset($form['field_canon_sibling'])) {
      $form['field_canon_sibling']['#access'] = FALSE;
    }
  }

  /**
   * Implements hook_form_FORM_ID_alter() for user_form.
   *
   * Alters the user form to ensure non-admins cannot edit protected fields.
   * Also changes the label on the cancel account button to be more clear.
   */
  #[Hook('form_user_form_alter')]
  public function formUserAlter(&$form, FormStateInterface $form_state, $form_id) {
    if (isset($form['actions']) && isset($form['actions']['delete'])) {
      $form['actions']['delete']['#title'] = $this->t('Delete account');
    }

    $user = User::load($this->current_user->id());
    if ($user && $user->hasRole('administrator')) {
      return;
    }

    $form['field_cover_artist_name']['#access'] = FALSE;
    $form['field_reader_name']['#access'] = FALSE;
    $form['field_author_name']['#access'] = FALSE;
    $form['contact']['#access'] = FALSE;
  }

  /**
   * Implements hook_inline_entity_form_table_fields_alter().
   *
   * Ensures that the inline entity table widget renders podfic files
   * in the order & with the labels we require.
   */
  #[Hook('inline_entity_form_table_fields_alter')]
  public function formInlineEnitityFormTableFieldsAlter(&$fields, $context) {
    $field_name = $context['field_name'];
    if (in_array($field_name, ['field_mp3_files', 'field_other_files'])) {
      unset($fields['label']);
    }

    switch ($field_name) {
      case 'field_mp3_files':
        $fields['field_media_audio_file'] = [
          'type' => 'field',
          'label' => $this->t('File'),
          'weight' => 1,
        ];

        $fields['field_chapter_label'] = [
          'type' => 'field',
          'label' => $this->t('Chapter label'),
          'weight' => 2,
        ];

        $fields['field_use_for_duration'] = [
          'type' => 'field',
          'label' => $this->t('Use for duration?'),
          'weight' => 3,
        ];
        break;

      case 'field_other_files':
        $fields['field_media_file'] = [
          'type' => 'field',
          'label' => $this->t('File'),
          'weight' => 0,
        ];

        $fields['field_file_label'] = [
          'type' => 'field',
          'label' => $this->t('File label'),
          'weight' => 1,
        ];
        break;
    }
  }

  /**
   * Alters the node creation form to insert the series creation fields.
   */
  private function insertSeriesCreationWidget(&$form) {
    $series_weight = $form['field_series']['#weight'];

    $form['create_series'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Create new series'),
      '#weight' => $series_weight + 1,
      '#group' => 'group_series_and_collections',
    ];

    $form['new_series_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('New series name'),
      '#weight' => $series_weight + 2,
      '#group' => 'group_series_and_collections',
      '#states' => [
        'visible' => [':input[name="create_series"]' => ['checked' => TRUE]],
      ],
    ];

    foreach (array_keys($form['actions']) as $action) {
      if ($action != 'preview' &&
          isset($form['actions'][$action]['#type']) &&
          $form['actions'][$action]['#type'] === 'submit'
      ) {
        $form['actions'][$action]['#submit'][] = static::class . '::nodeFormSubmitHandler';
      }
    }

    $form['#attached']['library'][] = 'aa_gin/add-new-tag';
  }

  /**
   * Alter tagify entity autocomplete widgets.
   *
   * Make them use a custom selection handler & ensure
   * the default help text is altered to be less confusing.
   */
  private function alterTagify(&$form) {
    foreach ($form as $key => $value) {
      if (!is_array($value)) {
        continue;
      }

      if (!isset($value['widget']) || !isset($value['widget']['#type'])) {
        continue;
      }

      if ($value['widget']['#type'] != 'entity_autocomplete_tagify') {
        continue;
      }

      $form[$key]['widget']['#selection_handler'] = 'search_index:taxonomy_term';
      $form[$key]['widget']['#selection_settings']['search_index'] = 'canon_taxonomy_terms';

      if (!isset($value['widget']['#description'])) {
        continue;
      }

      if (is_array($value['widget']['#description']) && isset($value['widget']['#description']['#items'])) {
        // Find Drag to re-order text in list and replace
        foreach ($value['widget']['#description']['#items'] as $desc_key => $description) {
          if (!($description instanceof TranslatableMarkup)) {
            continue;
          }

          if (str_starts_with($description->getUntranslatedString(), 'Drag to re-order')) {
            $form[$key]['widget']['#description']['#items'][$desc_key] = $this->t('Drag to re-order tags.');
          }
        }
      } else {
        // Replace description if it is Drag to re-order.
        $description = $value['widget']['#description'];
        if (!($description instanceof TranslatableMarkup)) {
          return;
        }

        if (str_starts_with($description->getUntranslatedString(), 'Drag to re-order')) {
          $form[$key]['widget']['#description'] = $this->t('Drag to re-order tags.');
        }
      }
    }
  }

  /**
   * Custom submit handler for node work forms.
   *
   * If the create_series checkbox is ticked and a new series name is provided,
   * this submit handler creates that new series and links the current work to
   * it.
   */
  public function nodeFormSubmitHandler(array $form, FormStateInterface $form_state) {
    $series_title = $form['new_series_name']['#value'];
    if (!$form['create_series']['#checked'] || empty($series_title)) {
      return;
    }

    $nid = $form_state->getformObject()->getEntity()->id();
    $series = Node::create([
      'type' => 'playlist',
      'title' => $series_title,
      'field_works_series' => $nid,
    ]);
    $series->save();
    $this->utils->setCollectionMetadata($series, [Node::load($nid)]);
  }

  /**
   * Ensures the default for the owner & reader fields is set to the current user.
   */
  private function prefillReaderOwnerFields(&$form) {
    $user = User::load($this->current_user->id());
    $reader_name = array_first($user->get('field_reader_name')->referencedEntities());

    if ($reader_name) {
      $this->fillWidgetDefaultValue($form, 'field_reader', $reader_name);
      $this->fillWidgetDefaultValue($form, 'field_owner', $reader_name);
    }
  }

  /**
   * Adds a tag to the default values list of a widget.
   */
  private function fillWidgetDefaultValue(&$form, string $field_name, TermInterface $tag) {
    if (isset($form[$field_name]['widget']['target_id'])) {
      $form[$field_name]['widget']['target_id']['#default_value'][] = $tag;
    } else {
      $form[$field_name]['widget']['#default_value'][] = $tag;
    }
  }

}
