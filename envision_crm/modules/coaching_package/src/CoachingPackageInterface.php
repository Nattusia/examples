<?php

namespace Drupal\coaching_package;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a coaching package entity type.
 */
interface CoachingPackageInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

  /**
   * Gets the coaching package title.
   *
   * @return string
   *   Title of the coaching package.
   */
  public function getTitle();

  /**
   * Sets the coaching package title.
   *
   * @param string $title
   *   The coaching package title.
   *
   * @return \Drupal\coaching_package\CoachingPackageInterface
   *   The called coaching package entity.
   */
  public function setTitle($title);

  /**
   * Gets the coaching package creation timestamp.
   *
   * @return int
   *   Creation timestamp of the coaching package.
   */
  public function getCreatedTime();

  /**
   * Sets the coaching package creation timestamp.
   *
   * @param int $timestamp
   *   The coaching package creation timestamp.
   *
   * @return \Drupal\coaching_package\CoachingPackageInterface
   *   The called coaching package entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the coaching package status.
   *
   * @return bool
   *   TRUE if the coaching package is enabled, FALSE otherwise.
   */
  public function isEnabled();

  /**
   * Sets the coaching package status.
   *
   * @param bool $status
   *   TRUE to enable this coaching package, FALSE to disable.
   *
   * @return \Drupal\coaching_package\CoachingPackageInterface
   *   The called coaching package entity.
   */
  public function setStatus($status);

}
