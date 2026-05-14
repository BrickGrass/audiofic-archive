<?php

namespace Drupal\audiofic_archive_claim_tag_form\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Implements hooks for the Audiofic Archive Claim Tag Form module.
 */
class AudioficArchiveClaimTagFormHooks {
  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entity_type_manager,
  ) {}

  /**
   * Implements hook_mail().
   *
   * TODO: This email is in plain text and is very bare bones! Once core
   * fully supports html email via the symfony mailer, see:
   * https://www.drupal.org/project/drupal/issues/1803948
   * we should think about making this email look nice & link to
   * the user & tags in question!
   */
  #[Hook('mail')]
  public function mail($key, &$message, $params) {
    switch ($key) {
      case 'claim_attribution_tag':

        $body = [];
        $body[] = $this->t('The user @user with user id @uid wants to claim the following attribution tags:', [
          '@user' => $params['username'],
          '@uid' => $params['user_id'],
        ]);
        foreach ($params['tags'] as $tag_type => $tags) {
          if (empty($tags)) {
            continue;
          }

          $body[] = $this->buildTagSummary($tag_type, $tags);
        }
        $body[] = '';
        $body[] = $this->t('Message from the user:');
        $body[] = $params['message'];

        $message['from'] = \Drupal::config('system.site')->get('mail');
        $message['subject'] = $this->t('The user @user wants to claim attribution tags', [
          '@user' => $params['username'],
        ]);
        $message['body'][] = implode("\n", $body);
        break;
    }
  }

  /**
   * Build a string containing a list of terms passed in + their type.
   */
  private function buildTagSummary(string $tag_type, array $term_array): string {
    $term_ids = array_column($term_array, 'target_id');
    $terms = $this->entity_type_manager->getStorage('taxonomy_term')->loadMultiple($term_ids);
    $term_strings = array_map(function ($term) {
        return $term->getName() . ' (' . $term->id() . ')';
    }, $terms);

    switch ($tag_type) {
      case 'reader_tags':
        return $this->t('Reader tags: @tags', ['@tags' => implode(', ', $term_strings)]);

      case 'author_tags':
        return $this->t('Author tags: @tags', ['@tags' => implode(', ', $term_strings)]);

      case 'cover_artist_tags':
        return $this->t('Cover Artist tags: @tags', ['@tags' => implode(', ', $term_strings)]);

      default:
        return '';
    }
  }

}
