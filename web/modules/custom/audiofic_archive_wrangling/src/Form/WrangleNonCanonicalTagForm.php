<?php

namespace Drupal\audiofic_archive_wrangling\Form;

use Drupal\aa_utils\Service\AudioficTagUtils;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\redirect\Entity\Redirect;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;

/**
 * Form which allows archivists to wrangle a user-created tag.
 */
class WrangleNonCanonicalTagForm extends FormBase {

  /**
   * The taxonomy term to wrangle.
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
   * The Entity Field Manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $entityFieldManager;

  /**
   * The Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  public function __construct(AudioficTagUtils $tag_utils, EntityFieldManager $entity_field_manager, EntityTypeManager $entity_type_manager) {
    $this->tagUtils = $tag_utils;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('aa_utils.tag_utils'),
      $container->get('entity_field.manager'),
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
    if (empty($taxonomy_term)) {
      throw new MissingMandatoryParametersException('wrangling.wrangle_non_canonical_tag_form', ['term']);
    }
    $this->term = $taxonomy_term;
    $term_name = $this->term->getName();

    if (!$this->tagUtils->isTagCanonicityAware($this->term)) {
      throw new \Exception("The term $term_name is not canonicity aware, it cannot be wrangled!");
    }

    $canonicity = $this->term->get('field_canonicity')->value;
    if (!in_array($canonicity, ['non_canon', 'not_wrangleable', 'unsorted'])) {
      throw new \Exception("The term $term_name is canonical, only non-canonical tags can be edited using this form.");
    }

    $form['tag_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Tag Name'),
      '#default_value' => $this->term->getName(),
      '#disabled' => TRUE,
    ];

    $field_definitions = $this->entityFieldManager->getFieldDefinitions('taxonomy_term', $this->term->bundle());
    $allowed_options = options_allowed_values($field_definitions['field_canonicity']->getFieldStorageDefinition());
    unset($allowed_options['canon']);
    unset($allowed_options['canonical_root_fandom']);

    $form['canonicity'] = [
      '#type' => 'select',
      '#title' => $this->t('Tag Status'),
      '#options' => $allowed_options,
      '#default_value' => $canonicity,
      // Caching doesn't respect default_value otherwise.
      '#attributes' => [
        'autocomplete' => 'off',
      ],
    ];

    $form['canon_sibling'] = [
      '#type' => 'entity_autocomplete_tagify',
      '#title' => $this->t('Canonical Synonym'),
      '#target_type' => 'taxonomy_term',
      '#tags' => FALSE,
      '#selection_handler' => 'search_index:taxonomy_term',
      '#selection_settings' => [
        'target_bundles' => [$this->term->bundle()],
        'search_index' => 'canon_taxonomy_terms',
      ],
      '#suggestions_dropdown' => 1,
      '#match_operator' => 'CONTAINS',
      '#states' => [
        'visible' => [
          ':input[name="canonicity"]' => ['value' => 'non_canon'],
        ],
      ],
    ];
    $sibling = $this->term->get('field_canon_sibling')->referencedEntities();
    if (!empty($sibling)) {
      $form['canon_sibling']['#default_value'] = $sibling;
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getValue('canonicity') != 'non_canon') {
      return;
    }

    if (empty($form_state->getValue('canon_sibling'))) {
      $form_state->setErrorByName('canon_sibling', $this->t('A canonical synonym must be defined if a tag is non-canonical!'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $canonicity = $form_state->getValue('canonicity');
    $this->term->set('field_canonicity', $canonicity);

    $canon_sibling = $form_state->getValue('canon_sibling');
    if ($canonicity == 'non_canon' && !empty($canon_sibling)) {
      $canon_sibling_arr = json_decode($canon_sibling);
      $tid = array_first($canon_sibling_arr)->value;
      $canon_sibling_term = $this->entityTypeManager->getStorage('taxonomy_term')->load($tid);
      if (empty($canon_sibling_term)) {
        throw new \Exception("Error loading taxonomy term with tid: $tid");
      }

      $this->term->set('field_canon_sibling', $canon_sibling_term);
      $this->createRedirect($this->term, $canon_sibling_term);
    } else {
      $previous_canon_sibling = $this->term->get('field_canon_sibling')->referencedEntities();
      if (!empty($previous_canon_sibling)) {
        $this->removeRedirect($this->term, $previous_canon_sibling[0]);
      }

      $this->term->set('field_canon_sibling', NULL);
    }

    $this->term->save();
    $this->messenger()->addStatus(
      $this->t('Updated tag @tag', ['@tag' => $this->term->getName()]));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return "wrangle_non_canonical_tag_form";
  }

  /**
   * Creates a redirect between two terms.
   */
  private function createRedirect(TermInterface $source, TermInterface $destination) {
    $existing_redirect_ids = $this->entityTypeManager->getStorage('redirect')->getQuery()
      ->accessCheck(FALSE)
      ->condition('redirect_source.path', 'taxonomy/term/' . $source->id())
      ->execute();

    $redirect_already_exists = FALSE;
    foreach (Redirect::loadMultiple($existing_redirect_ids) as $existing_redirect) {
      if ($existing_redirect->getRedirect()['uri'] == 'internal:/taxonomy/term/' . $destination->id()) {
        $redirect_already_exists = TRUE;
        continue;
      }

      $existing_redirect->delete();
    }

    if ($redirect_already_exists) {
      return;
    }

    $redirect = Redirect::create([
      'redirect_source' => [
        'path' => 'taxonomy/term/' . $source->id(),
        'query' => [],
      ],
      'redirect_redirect' => [
        'uri' => 'internal:/taxonomy/term/' . $destination->id(),
        'options' => [],
      ],
      'status_code' => '301',
    ]);
    $redirect->save();
  }

  /**
   * Removes any redirects that exist between two terms.
   */
  private function removeRedirect(TermInterface $source, TermInterface $destination) {
    $existing_redirect_ids = $this->entityTypeManager->getStorage('redirect')->getQuery()
      ->accessCheck(FALSE)
      ->condition('redirect_source.path', 'taxonomy/term/' . $source->id())
      ->execute();

    foreach (Redirect::loadMultiple($existing_redirect_ids) as $existing_redirect) {
      if ($existing_redirect->getRedirect()['uri'] != 'internal:/taxonomy/term/' . $destination->id()) {
        continue;
      }

      $existing_redirect->delete();
    }
  }

}
