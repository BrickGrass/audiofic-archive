<?php

namespace Drupal\audiofic_archive_wrangling\Form;

use Drupal\aa_utils\Service\AudioficTagUtils;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form which allows archivists to create/edit root fandom canonical tags.
 */
class WrangleRootFandomTagForm extends FormBase {

  /**
   * The taxonomy term to wrangle, or NULL if creating a new root fandom.
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
    // If updating an existing root fandom, validate it.
    if (!empty($taxonomy_term)) {
      $this->term = $taxonomy_term;
      $term_name = $this->term->getName();

      if (!$this->tagUtils->isTagCanonicityAware($this->term)) {
        throw new \Exception("The term $term_name is not canonicity aware, it cannot be wrangled!");
      }

      $canonicity = $this->term->get('field_canonicity')->value;
      if ($canonicity != 'canonical_root_fandom') {
        throw new \Exception("The term $term_name is not a root fandom, only root fandom tags can be edited using this form.");
      }
    }

    $form['tag_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Tag Name'),
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    if (!empty($taxonomy_term)) {
      $form['tag_name']['#default_value'] = $this->term->getName();
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
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $name = $form_state->getValue('tag_name');

    if (empty($this->term)) {
      // Creating a new root fandom term.
      /** @var \Drupal\taxonomy\TermStorage $term_storage */
      $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');
      $new_term = $term_storage->create([
        'name' => $name,
        'vid' => 'fandom',
        'field_canonicity' => 'canonical_root_fandom',
      ]);
      $new_term->save();
      $this->messenger()->addStatus(
        $this->t('Root fandom tag @tag created', ['@tag' => $name]));
      // $form_state->setRedirect('audiofic_archive_wrangling.wrangle_canonical', ['taxonomy_term' => $new_term->id()]);
    } else {
      // Editing an existing root fandom term.
      $this->term->setName($name);
      $this->term->save();
      $this->messenger()->addStatus(
        $this->t('Updated root fandom tag @tag', ['@tag' => $name]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return "wrangle_root_fandom_tag_form";
  }

}
