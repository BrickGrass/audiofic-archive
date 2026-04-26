<?php

namespace Drupal\propagate_metadata\Plugin\Validation\Constraint;

use Drupal\aa_utils\Service\AudioficTagUtils;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates that a canonical tag does not have a canonical sibling.
 */
class CanonicalTagCannotHaveSiblingConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * The TagUtils Service.
   *
   * @var \Drupal\aa_utils\Service\AudioficTagUtils
   */
  protected $tagUtils;

  public function __construct(protected AudioficTagUtils $tag_utils) {
    $this->tagUtils = $tag_utils;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('aa_utils.tag_utils'));
  }

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint) {
    if (!$value instanceof TermInterface) {
      return;
    }

    if (!$this->tagUtils->isTagCanonicityAware($value)) {
      return;
    }

    if ($value->get('field_canonicity')->value != 'canon') {
      return;
    }

    $canonical_sibling = array_first(array_column($value->get('field_canon_sibling')->getValue(), 'target_id'));
    if (!empty($canonical_sibling)) {
      $this->context->addViolation($constraint->errorMessage, [
        '%tag_name' => $value->getName(),
      ]);
    }
  }

}
