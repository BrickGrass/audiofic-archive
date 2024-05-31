<?php

// example/src/ExampleInterface.php
// Assuming that your configuration entity has properties, you will need to define some set/get methods on an interface.

namespace Drupal\access_by_ref;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining an Example entity.
 */
interface AbrconfigInterface extends ConfigEntityInterface {

  /**
   * Get the bundle of the abr config
   *
   * @return string
   */
  public function getBundle();

  /**
   * Set the bundle of the abr config
   *
   * @param string $bundle
   *
   * @return $this
   */
  public function setBundle($bundle);

  /**
   * Get the field of the abr config
   *
   * @return string
   */
  public function getField();

  /**
   * Set the field of the abr config
   *
   * @param string $field
   *
   * @return $this
   */
  public function setField($field);

  /**
   * Het the bundle of the abr config
   *
   * @return string
   */

  public function getReferenceType();

  /**
   * Set a config value
   *
   * @param string $reference_type
   *
   * @return $this
   */
  public function setReferenceType($reference_type);

  /**
   * Get the extra field of the abr config
   *
   * @return string
   */

  public function getExtra();

  /**
   * Set a config value
   *
   * @param string $extra
   *
   * @return $this
   */
  public function setExtra($extra);

  /**
   * Get the rights type of the abr config
   *
   * @param bool $readable
   *
   * @return string
   */

  public function getRightsType($readable);

  /**
   * Set the rights type of the abr config
   *
   * @param string $rights_type
   *
   * @return $this
   */
  public function setRightsType($rights_type);

  /**
   * Get the rights read of the abr config
   *
   * @return bool
   */

  public function getRightsRead();

  /**
   * Set the rights read of the abr config
   *
   * @param bool $rights_read
   *
   * @return $this
   */
  public function setRightsRead($rights_read);

  /**
   * Get the rights update of the abr config
   *
   * @return bool
   */

  public function getRightsUpdate();

  /**
   * Set the rights update of the abr config
   *
   * @param bool $rights_update
   *
   * @return $this
   */
  public function setRightsUpdate($rights_update);

  /**
   * Get the rights delete of the abr config
   *
   * @return bool
   */

  public function getRightsDelete();

  /**
   * Set the rights delete of the abr config
   *
   * @param bool $rights_delete
   *
   * @return $this
   */
  public function setRightsDelete($rights_delete);


}