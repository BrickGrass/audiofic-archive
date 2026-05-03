<?php

namespace Drupal\audiofic_archive_wrangling\Form;

use Drupal\aa_utils\Service\AudioficTagUtils;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form which allows archivists to wrangle/create a canonical tag.
 */
class WrangleCanonicalTagForm extends FormBase {

  /**
   * The taxonomy term to wrangle, or NULL if creating a new canonical.
   *
   * @var \Drupal\taxonomy\TermInterface
   */
  protected $term;

  /**
   * The Audiofic Tag Utils Service.
   *
   * @var \Drupal\aa_utils\Service\AudioficTagUtils
   */
  protected $tagUtils;

  /**
   * The Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  public function __construct(AudioficTagUtils $tag_utils, EntityTypeManager $entity_type_manager) {
    $this->tagUtils = $tag_utils;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('aa_utils.tag_utils'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    ?TermInterface $taxonomy_term = NULL,
  ) {
    // If updating an existing canonical, validate it.
    if (!empty($taxonomy_term)) {
      $this->term = $taxonomy_term;
      $term_name = $this->term->getName();

      if (!$this->tagUtils->isTagCanonicityAware($this->term)) {
        throw new \Exception("The term $term_name is not canonicity aware, it cannot be wrangled!");
      }

      $canonicity = $this->term->get('field_canonicity')->value;
      if ($canonicity != 'canon') {
        throw new \Exception("The term $term_name is not canonical, only canonical tags can be edited using this form.");
      }
    }

    $form['tag_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Tag Name'),
    ];

    $form['tag_vocabulary'] = [
      '#type' => 'select',
      '#title' => $this->t('Tag Vocabulary'),
      '#options' => [
        '' => $this->t('--- Select a tag vocabulary ---'),
        'fandom' => $this->t('Fandom'),
        'relationship' => $this->t('Relationship'),
      ],
      // Caching doesn't respect default_value otherwise.
      '#attributes' => [
        'autocomplete' => 'off',
      ],
    ];

    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $root_fandoms = [];
    $root_fandoms[0] = $this->t('--- Select a root fandom ---');
    foreach ($term_storage->loadMultiple($this->tagUtils->getRootFandomIds()) as $tid => $term) {
      $root_fandoms[$tid] = $term->getName();
    }
    uasort($root_fandoms, function ($a, $b) {
      if ($a == $b) {
        return 0;
      }
      return ($a < $b) ? -1 : 1;
    });

    $form['root_fandom'] = [
      '#type' => 'select',
      '#title' => $this->t('Root Fandom'),
      '#options' => $root_fandoms,
      // Caching doesn't respect default_value otherwise.
      '#attributes' => [
        'autocomplete' => 'off',
      ],
      '#states' => [
        'visible' => [':input[name="tag_vocabulary"]' => ['value' => 'fandom']],
      ],
    ];

    $form['parent_fandoms'] = [
      '#type' => 'entity_autocomplete_tagify',
      '#title' => $this->t('Parent Fandom(s)'),
      '#target_type' => 'taxonomy_term',
      '#tags' => TRUE,
      '#selection_handler' => 'search_index:taxonomy_term',
      '#selection_settings' => [
        'target_bundles' => ['fandom'],
        'search_index' => 'canon_taxonomy_terms',
      ],
      '#suggestions_dropdown' => 1,
      '#match_operator' => 'CONTAINS',
      '#states' => [
        'visible' => [
          ':input[name="tag_vocabulary"]' => ['value' => 'fandom'],
        ],
      ],
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    if (!empty($this->term)) {
      $this->setDefaultValues($form);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (empty($form_state->getValue('tag_name'))) {
      $form_state->setErrorByName('tag_name', $this->t('Tags must have a name!'));
    }

    $vocab = $form_state->getValue('tag_vocabulary');
    if (!in_array($vocab, ['fandom', 'relationship'])) {
      $form_state->setErrorByName('tag_vocabulary', $this->t('You must select a tag vocabulary!'));
    }

    if ($vocab == 'fandom') {
      if (empty($form_state->getValue('root_fandom'))) {
        $form_state->setErrorByName('root_fandom', $this->t('You must select a root fandom!'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $name = $form_state->getValue('tag_name');
    $vocab = $form_state->getValue('tag_vocabulary');

    if (empty($this->term)) {
      // Creating a new canonical term.
      /** @var \Drupal\taxonomy\TermStorage $term_storage */
      $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
      $term_values = [
        'name' => $name,
        'vid' => $vocab,
        'field_canonicity' => 'canon',
      ];
      if ($vocab == 'fandom') {
        $term_values['parent'] = $this->getRequestedParents($form_state);
      }
      $new_term = $term_storage->create($term_values);
      $new_term->save();
      $this->messenger()->addStatus(
        $this->t('Canonical tag @tag created', ['@tag' => $name]));
      $form_state->setRedirect('audiofic_archive_wrangling.wrangle_root_fandom', ['taxonomy_term' => $new_term->id()]);
    } else {
      // Editing an existing canonical term.
      $this->term->setName($name);
      if ($vocab == 'fandom') {
        $this->term->set('parent', $this->getRequestedParents($form_state));
      }
      $this->term->save();
      $this->messenger()->addStatus(
        $this->t('Updated canonical tag @tag', ['@tag' => $name]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return "wrangle_canonical_tag_form";
  }

  /**
   * Fetches an array of the parent tags requested in the form.
   */
  private function getRequestedParents(FormStateInterface $form_state): array {
    $root_fandom_id = $form_state->getValue('root_fandom');
    $parents_str = $form_state->getValue('parent_fandoms');

    if (empty($parents_str)) {
      return [$root_fandom_id];
    }

    $root_fandom_ids = $this->tagUtils->getRootFandomIds();
    $parent_ids = [];
    foreach (json_decode($parents_str) as $item) {
      // Ensure only one root fandom is included.
      if (in_array($item->value, $root_fandom_ids)) {
        continue;
      }
      $parent_ids[] = $item->value;
    }
    $parent_ids[] = $root_fandom_id;
    return $parent_ids;
  }

  /**
   * Sets the default values of all form fields, based on the term being edited.
   */
  private function setDefaultValues(&$form) {
    $form['tag_name']['#default_value'] = $this->term->getName();
    $form['tag_vocabulary']['#default_value'] = $this->term->bundle();
    $form['tag_vocabulary']['#type'] = 'hidden';

    if ($this->term->bundle() == 'fandom') {
      $root_fandom_ids = $this->tagUtils->getRootFandomIds();
      /** @var \Drupal\taxonomy\TermStorage $term_storage */
      $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
      $term_parents = $term_storage->loadParents($this->term->id());

      foreach ($term_parents as $tid => $parent) {
        if (in_array($tid, $root_fandom_ids)) {
          $form['root_fandom']['#default_value'] = $parent->id();
          unset($term_parents[$tid]);
          break;
        }
      }

      $form['parent_fandoms']['#default_value'] = $term_parents;
    }
  }

}
