<?php

namespace Drupal\Tests\access_by_ref\Traits;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\User;

/**
 * Provides a helper method for creating a repository content type with fields.
 */
trait AbrUserTrait {

  /**
   * User with permission to administer abr.
   *
   * @var \Drupal\user\Entity\User|false
   */
  protected User|FALSE $abrAdminUser;

  /**
   * User with permission to use abr.
   *
   * @var \Drupal\user\Entity\User|false
   */
  protected User|FALSE $abrAllowedUser;

  /**
   * User with permission to use abr.
   *
   * @var \Drupal\user\Entity\User|false
   */
  protected User|FALSE $abrOtherUser;

  /**
   * User with permission to use abr.
   *
   * @var \Drupal\user\Entity\User|false
   */
  protected User|FALSE $nonAbrUser;

}
