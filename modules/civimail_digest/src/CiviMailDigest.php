<?php

namespace Drupal\civimail_digest;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Core\Database\Driver\mysql\Connection;
use Drupal\civicrm_tools\CiviCrmApiInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class CiviMailDigest.
 */
class CiviMailDigest implements CiviMailDigestInterface {

  /**
   * Drupal\Core\Database\Driver\mysql\Connection definition.
   *
   * @var \Drupal\Core\Database\Driver\mysql\Connection
   */
  protected $database;

  /**
   * Drupal\civicrm_tools\CiviCrmApiInterface definition.
   *
   * @var \Drupal\civicrm_tools\CiviCrmApiInterface
   */
  protected $civicrmToolsApi;

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\Core\Config\ConfigFactoryInterface definition.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Drupal\Core\Config\ImmutableConfig definition.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  private $digestConfig;

  /**
   * Constructs a new CiviMailDigest object.
   */
  public function __construct(Connection $database, CiviCrmApiInterface $civicrm_tools_api, EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory) {
    $this->database = $database;
    $this->civicrmToolsApi = $civicrm_tools_api;
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
    $this->digestConfig = $this->configFactory->get('civimail_digest.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function isActive() {
    $result = FALSE;
    if (!$this->digestConfig->get('is_active')) {
      \Drupal::messenger()->addWarning(t('The digest feature is not enabled.'));
    }
    else {
      $result = TRUE;
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function hasNextDigestContent() {
    return !empty($this->prepareDigestContent());
  }

  /**
   * Get the content entities keys that are candidates for a digest.
   *
   * These candidates are evaluated from CiviMail mailings that were
   * previously sent and the configured limitations.
   *
   * @return array
   *   Content entities result from the {civimail_entity_mailing} table.
   */
  private function prepareDigestContent() {
    $result = [];
    if ($this->isActive()) {
      // @todo assert all the values and send to configuration if not valid.
      $quantityLimit = $this->digestConfig->get('quantity_limit');
      $language = $this->digestConfig->get('language');
      $includeUpdate = $this->digestConfig->get('include_update');

      $configuredBundles = $this->digestConfig->get('bundles');
      $bundles = [];
      // Get rid of the keys, take only values if they are the same.
      foreach ($configuredBundles as $key => $configuredBundle) {
        if ($configuredBundle === $key) {
          $bundles[] = $configuredBundle;
        }
      }

      $maxDays = $this->digestConfig->get('age_in_days');
      // @todo get from system settings
      $timeZone = new \DateTimeZone('Europe/Brussels');
      $contentAge = new \DateTime('now -' . $maxDays . ' day', $timeZone);

      // Get all the CiviMail mailings for entities that are matching
      // the configuration limitations.
      $civiMailQuery = $this->database->select('civimail_entity_mailing', 'cem')
        ->fields('cem', [
          'entity_id',
          'entity_bundle',
          'langcode',
          'civicrm_mailing_id',
          'timestamp',
        ]
      );
      $civiMailQuery->condition('cem.timestamp', $contentAge->getTimestamp(), '>');
      // @todo extend to other entity types
      $civiMailQuery->condition('cem.entity_type_id', 'node');
      $civiMailQuery->condition('cem.entity_bundle', $bundles, 'IN');
      $civiMailQuery->condition('cem.langcode', $language);
      $civiMailQuery->orderBy('cem.timestamp', 'DESC');
      $civiMailQuery->range(0, $quantityLimit);
      $civiMailResult = $civiMailQuery->execute()->fetchAll();

      // Store a reference of all the mailings for candidate entities.
      // @todo extend to other entity types
      $candidateEntities = [
        'node' => [],
      ];
      foreach ($civiMailResult as $row) {
        if (empty($candidateEntities['node'][$row->entity_id])) {
          $candidateEntities['node'][$row->entity_id] = ['mailing' => [$row->civicrm_mailing_id]];
        }
        else {
          $candidateEntities['node'][$row->entity_id]['mailing'][] = $row->civicrm_mailing_id;
        }
      }

      // @todo compare with what was sent previously

      if ($includeUpdate) {
        // @todo include update case
      }

      // Maps all the candidate entities as a plain list of entity ids
      // grouped by entity type so they can then be loaded easily.
      foreach ($candidateEntities as $entityTypeId => $entities) {
        $result[$entityTypeId] = [];
        foreach ($entities as $entityId => $entityLog) {
          $result[$entityTypeId][] = $entityId;
        }
      }
    }
    return $result;
  }

  /**
   * Get the content entities keys that are candidates for a digest.
   *
   * These candidates are evaluated from CiviMail mailings that were
   * previously sent and the configured limitations.
   *
   * @return array
   *   Content entities result from the {civimail_entity_mailing} table.
   */
  private function getDigestContent($digest_id) {
    $result = [];
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function previewDigest() {
    $content = $this->prepareDigestContent();
    $digest = [];
    if (!empty($content)) {
      $entities = $this->getDigestEntities($content);
      $digest = $this->buildDigest($entities);
    }
    return $this->getDigestAsResponse($digest);
  }

  /**
   * {@inheritdoc}
   */
  public function viewDigest($digest_id) {
    $content = $this->getDigestContent($digest_id);
    $digest = [];
    if (!empty($content)) {
      $entities = $this->getDigestEntities($content);
      $digest = $this->buildDigest($entities);
    }
    return $this->getDigestAsResponse($digest);
  }

  /**
   * Renders a digest and wrap it into a Response.
   *
   * @param array $digest
   *   Digest render array.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Digest response.
   */
  private function getDigestAsResponse(array $digest) {
    // @todo dependency injection
    /** @var \Drupal\Core\Render\Renderer $renderer */
    $renderer = \Drupal::service('renderer');
    if (!empty($digest)) {
      $output = $renderer->renderRoot($digest);
    }
    else {
      $noResults = [
        '#markup' => t('No content for the digest.'),
      ];
      $output = $renderer->renderRoot($noResults);
    }
    return new Response($output);
  }

  /**
   * Loads the entities and prepares the view modes for the digest content.
   *
   * @param array $content
   *   List of entities grouped by entity types.
   *
   * @return array
   *   List of rendered entities.
   */
  private function getDigestEntities(array $content) {
    $result = [];
    // @todo assert defined
    $digestViewMode = $this->digestConfig->get('view_mode');
    foreach ($content as $entityTypeId => $entityIds) {
      try {
        $entities = $this->entityTypeManager->getStorage($entityTypeId)->loadMultiple($entityIds);
        foreach ($entities as $entity) {
          $viewBuilder = $this->entityTypeManager->getViewBuilder($entityTypeId);
          $view = $viewBuilder->view($entity, $digestViewMode);
          $renderedView = \Drupal::service('renderer')->renderRoot($view);
          $result[] = $renderedView;
        }
      }
      catch (InvalidPluginDefinitionException $exception) {
        \Drupal::messenger()->addError($exception->getMessage());
      }
    }
    return $result;
  }

  /**
   * Builds the rendered array for a digest.
   *
   * @param array $entities
   *   List of rendered entities.
   * @param int $digest_id
   *   Digest id.
   *
   * @return array
   *   Render array of the digest.
   */
  private function buildDigest(array $entities, $digest_id = NULL) {
    // @todo add text
    // @todo refactor CiviMail service
    $currentDigestId = $digest_id;
    if (is_null($digest_id)) {
      // @todo get it by incrementing the last digest id.
      $currentDigestId = 0;
    }
    return [
      '#theme' => 'civimail_digest_html',
      '#entities' => $entities,
      '#digest_title' => $this->getDigestTitle($currentDigestId),
      '#digest_id' => $currentDigestId,
      // Use CiviCRM token.
      '#civicrm_unsubscribe_url' => '{action.unsubscribeUrl}',
      // Allows template overrides to load assets provided by the current theme
      // with {{ base_path ~ directory }}.
      '#base_path' => \Drupal::request()->getSchemeAndHttpHost() . '/',
      '#absolute_link' => $this->getAbsoluteDigestLink($currentDigestId),
      '#absolute_url' => $this->getAbsoluteDigestUrl($currentDigestId),
    ];
  }

  /**
   * Returns the digest title.
   *
   * @param int $digest_id
   *   Digest id.
   *
   * @return string
   *   Digest title.
   */
  private function getDigestTitle($digest_id = NULL) {
    return $this->digestConfig->get('digest_title');
  }

  /**
   * Returns the absolute digest url.
   *
   * @param int $digest_id
   *   Digest id.
   *
   * @return \Drupal\Core\Url
   *   Digest url.
   */
  private function getAbsoluteDigestUrl($digest_id) {
    return Url::fromRoute('civimail_digest.view', ['digest_id' => $digest_id])->setAbsolute();
  }

  /**
   * Returns an absolute link to a digest view.
   *
   * @param int $digest_id
   *   Digest id.
   *
   * @return array|\Drupal\Core\Link
   *   Absolute link to the digest.
   */
  private function getAbsoluteDigestLink($digest_id) {
    $link = Link::fromTextAndUrl(t('View it online'), $this->getAbsoluteDigestUrl($digest_id));
    $link = $link->toRenderable();
    return $link;
  }

  /**
   * Creates a new digest id in the digest table and returns it.
   *
   * @return int
   *   The digest id.
   */
  public function createDigest() {
    $result = NULL;
    try {
      $fields = [
        'status' => CiviMailDigestInterface::STATUS_CREATED,
        'timestamp' => \Drupal::time()->getRequestTime(),
      ];
      // Returns the serial id of the digest.
      $result = $this->database->insert('civimail_digest')
        ->fields($fields)
        ->execute();
    }
    catch (\Exception $exception) {
      \Drupal::logger('civimail_digest')->error($exception->getMessage());
      \Drupal::messenger()->addError($exception->getMessage());
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareDigest() {
    $content = $this->prepareDigestContent();
    if (!empty($content)) {
      $digestId = $this->createDigest();
      if (NULL !== $digestId) {
        // Store each mailing id for an entity and store a digest reference.
        // Set then the status to 1.
      }
    }
    // TODO: Implement prepareDigest() method.
  }

  /**
   * {@inheritdoc}
   */
  public function getDigests() {
    // TODO: Implement getDigests() method.
  }

  /**
   * {@inheritdoc}
   */
  public function notifyValidators() {
    // TODO: Implement notifyValidators() method.
  }

  /**
   * {@inheritdoc}
   */
  public function sendTestDigest($digest_id) {
    // TODO: Implement sendTestDigest() method.
  }

  /**
   * {@inheritdoc}
   */
  public function sendDigest($digest_id) {
    // TODO: Implement sendDigest() method.
    if ($this->isActive()) {
      /** @var \Drupal\civimail\CiviMailInterface $civiMail */
      $civiMail = \Drupal::service('civimail');
      // If success set the civimail id in the civimail digest table
      // and set the status to 2.
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDigestStatusLabel($status_id) {
    $result = t('Unknown status');
    switch ($status_id) {
      case CiviMailDigestInterface::STATUS_CREATED:
        $result = t('Created');
        break;

      case CiviMailDigestInterface::STATUS_PREPARED:
        $result = t('Prepared');
        break;

      case CiviMailDigestInterface::STATUS_SENT:
        $result = t('Sent');
        break;

      case CiviMailDigestInterface::STATUS_FAILED:
        $result = t('Failed');
        break;
    }
    return $result;
  }

}
