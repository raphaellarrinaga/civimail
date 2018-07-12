<?php

namespace Drupal\civimail_digest;

/**
 * Interface CiviMailDigestInterface.
 */
interface CiviMailDigestInterface {

  /**
   * Checks if the digest to be prepared has content.
   *
   * @return bool
   *   The content status for the digest.
   */
  public function hasNextDigestContent();

  /**
   * Collects the nodes that must be part of the digest.
   *
   * As a side effect, it assigns a digest id to each content entity
   * based on the limitations.
   *
   * @return int
   *   Digest id.
   */
  public function prepareDigest();

  /**
   * Previews the digest before its preparation.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Digest preview.
   */
  public function previewDigest();

  /**
   * Views a digest that has already been prepared.
   *
   * @param int $digest_id
   *   Digest id.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Prepared digest view.
   */
  public function viewDigest($digest_id);

  /**
   * Gets the digests with their status.
   *
   * @return array
   *   List of digests.
   */
  public function getDigests();

  /**
   * Notifies the validator groups if a new digest is ready.
   *
   * @return bool
   *   Status of the notification.
   */
  public function notifyValidators();

  /**
   * Sends a test digest to the configured test groups.
   *
   * @param int $digest_id
   *   Digest id.
   *
   * @return bool
   *   Digest send status.
   */
  public function sendTestDigest($digest_id);

  /**
   * Sends the digest to the configured groups.
   *
   * @param int $digest_id
   *   Digest id.
   *
   * @return bool
   *   Digest send status.
   */
  public function sendDigest($digest_id);

}
