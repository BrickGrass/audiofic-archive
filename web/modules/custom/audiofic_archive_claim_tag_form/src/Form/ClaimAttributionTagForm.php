<?php

namespace Drupal\audiofic_archive_claim_tag_form\Form;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageDefault;
use Drupal\Core\Mail\MailManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The ClaimAttributionTagForm allows logged in users to claim tags.
 *
 * It sends an email to an admin informing them that a user wishes to
 * claim 1 or more attribution tags.
 */
class ClaimAttributionTagForm extends FormBase {

  protected const FLOOD_LIMIT = 2;
  protected const FLOOD_INTERVAL = 3600;
  // TODO: Replace with the actual shared admin email we use!
  protected const ADMIN_EMAIL = 'admin@audiofic-archive.org';

  public function __construct(
    protected FloodInterface $flood,
    protected EntityTypeManagerInterface $entity_type_manager,
    protected DateFormatterInterface $date_formatter,
    protected MailManagerInterface $mail_manager,
    protected LanguageDefault $default_language,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('flood'),
      $container->get('entity_type.manager'),
      $container->get('date.formatter'),
      $container->get('plugin.manager.mail'),
      $container->get('language.default'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\user\UserInterface $user */
    $user = $this->entity_type_manager->getStorage('user')->load($this->currentUser()->id());
    if (empty($user)) {
      throw new \Exception('Could not fetch the current user, only logged in users can use this form.');
    }

    $form['user_details'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Your username and email will be shared with the administrator(s) this form is sent to so that we can communicate with you about this request. The email you have registered your account with is %email.', [
        '%email' => $user->getEmail(),
      ]),
    ];

    $form['reader_tags'] = [
      '#type' => 'entity_autocomplete_aa',
      '#title' => $this->t('Reader Tags'),
      '#description' => $this->t('Add the reader tags you wish to claim'),
      '#target_type' => 'taxonomy_term',
      '#tags' => TRUE,
      '#selection_handler' => 'search_index:taxonomy_term',
      '#selection_settings' => [
        'search_index' => 'canon_taxonomy_terms',
        'target_bundles' => ['reader'],
      ],
    ];

    $form['author_tags'] = [
      '#type' => 'entity_autocomplete_aa',
      '#title' => $this->t('Author Tags'),
      '#description' => $this->t('Add the author tags you wish to claim'),
      '#target_type' => 'taxonomy_term',
      '#tags' => TRUE,
      '#selection_handler' => 'search_index:taxonomy_term',
      '#selection_settings' => [
        'search_index' => 'canon_taxonomy_terms',
        'target_bundles' => ['author'],
      ],
    ];

    $form['cover_artist_tags'] = [
      '#type' => 'entity_autocomplete_aa',
      '#title' => $this->t('Cover Artist Tags'),
      '#description' => $this->t('Add the cover artist tags you wish to claim'),
      '#target_type' => 'taxonomy_term',
      '#tags' => TRUE,
      '#selection_handler' => 'search_index:taxonomy_term',
      '#selection_settings' => [
        'search_index' => 'canon_taxonomy_terms',
        'target_bundles' => ['cover_artist'],
      ],
    ];

    $form['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message'),
      '#description' => $this->t('To claim an attribution tag you will need to provide proof
                                  that you control the account that tag refers to on another
                                  website. Eg: you could respond to a comment we leave on one of
                                  your ao3 posts. Let us know by which route you would like to
                                  verify your identity, or let us know if you will have difficulty
                                  with this so we can work out another solution. This process is
                                  accelerated if you already have ownership of the same tag
                                  in a different category, eg: you own the reader tag "podficcer1"
                                  and now wish to claim the newly created author tag "podficcer1"'),
      '#rows' => 5,
      '#resizeable' => 'vertical',
      '#maxlength' => 5000,
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    $form['#attributes']['class'][] = 'p-md-3';
    $form['#attributes']['class'][] = 'pb-2';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (
      empty($form_state->getValue('reader_tags')) &&
      empty($form_state->getValue('author_tags')) &&
      empty($form_state->getValue('cover_artist_tags'))
    ) {
      $form_state->setErrorByName('', $this->t('You must select atleast one attribution tag to claim'));
    }

    // Check if flood control has been activated for sending emails.
    if (!$this->currentUser()->hasPermission('administer contact forms')) {
      if (!$this->flood->isAllowed('claim_attribution_tags', $this::FLOOD_LIMIT, $this::FLOOD_INTERVAL)) {
        $form_state->setErrorByName('', $this->t('You cannot send more than %limit messages in @interval. Try again later.', [
          '%limit' => $this::FLOOD_LIMIT,
          '@interval' => $this->date_formatter->formatInterval($this::FLOOD_INTERVAL),
        ]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $params = [
      'tags' => [
        'reader_tags' => $form_state->getValue('reader_tags'),
        'author_tags' => $form_state->getValue('author_tags'),
        'cover_artist_tags' => $form_state->getValue('cover_artist_tags'),
      ],
      'message' => $form_state->getValue('message'),
      'username' => $this->currentUser()->getAccountName(),
      'user_id' => $this->currentUser()->id(),
    ];

    $result = $this->mail_manager->mail(
      module: 'audiofic_archive_claim_tag_form',
      key: 'claim_attribution_tag',
      to: $this::ADMIN_EMAIL,
      langcode: $this->default_language->get()->getId(),
      params: $params,
      reply: $this->currentUser()->getEmail(),
      send: TRUE,
    );

    if ($result['result'] !== TRUE) {
      $this->messenger()->addError($this->t('Your message failed to send, please try again later.'));
      return;
    }

    $this->flood->register('claim_attribution_tags', $this::FLOOD_INTERVAL);

    $this->messenger()->addStatus($this->t('Your message was sent, please wait for the team to be in contact with you.'));

    // Redirect away to avoid false errors caused by flood control.
    $form_state->setRedirect('entity.user.canonical', ['user' => $this->currentUser()->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return "claim_attribution_tags_form";
  }

}
