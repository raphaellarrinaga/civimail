<?php

namespace Drupal\civimail_digest\Form;

use Drupal\civicrm_tools\CiviCrmGroupInterface;
use Drupal\civicrm_tools\CiviCrmContactInterface;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Class SettingsForm.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * Drupal\civicrm_tools\CiviCrmGroupInterface definition.
   *
   * @var \Drupal\civicrm_tools\CiviCrmGroupInterface
   */
  protected $civicrmToolsGroup;

  /**
   * Drupal\civicrm_tools\CiviCrmContactInterface definition.
   *
   * @var \Drupal\civicrm_tools\CiviCrmContactInterface
   */
  protected $civicrmToolsContact;

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new SettingsForm object.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    CiviCrmGroupInterface $civicrm_tools_group,
    CiviCrmContactInterface $civicrm_tools_contact,
    EntityTypeManagerInterface $entity_type_manager
    ) {
    parent::__construct($config_factory);
    $this->civicrmToolsGroup = $civicrm_tools_group;
    $this->civicrmToolsContact = $civicrm_tools_contact;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('civicrm_tools.group'),
      $container->get('civicrm_tools.contact'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'civimail_digest.settings',
    ];
  }

  /**
   * Returns a list of days.
   *
   * @return array
   *   List of week days.
   */
  private function getWeekDays() {
    // @todo review existing API
    return [
      0 => t('Sunday'),
      1 => t('Monday'),
      2 => t('Tuesday'),
      3 => t('Wednesday'),
      4 => t('Thursday'),
      5 => t('Friday'),
      6 => t('Saturday'),
    ];
  }

  /**
   * Returns a list of hours.
   *
   * @return array
   *   List of hours.
   */
  private function getHours() {
    // @todo review existing API
    $result = [];
    for ($h = 0; $h < 24; $h++) {
      $result[$h] = $h . ':00';
    }
    return $result;
  }

  /**
   * Returns a list of bundles currently limited to node type.
   *
   * @return array
   *   List of bundles.
   */
  private function getBundles() {
    $result = [];
    try {
      // @todo extend to other entity types
      $nodeBundles = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
      foreach ($nodeBundles as $key => $bundle) {
        $result[$key] = $bundle->label();
      }
    }
    catch (InvalidPluginDefinitionException $exception) {
      $exception->getMessage();
    }
    return $result;
  }

  /**
   * Returns all the groups, to be used as select options.
   *
   * @return array
   *   List of CiviCRM groups.
   */
  private function getGroups() {
    $result = [];
    $groups = $this->civicrmToolsGroup->getAllGroups();
    foreach ($groups as $key => $group) {
      $result[$key] = $group['title'];
    }
    return $result;
  }

  /**
   * Returns a list of contacts for a group, to be used as select options.
   *
   * @param array $groups
   *   CiviCRM array of group ids.
   *
   * @return array
   *   List of CiviCRM contacts.
   */
  private function getContacts(array $groups) {
    $result = [];
    $contacts = $this->civicrmToolsContact->getFromGroups($groups);
    foreach ($contacts as $key => $contact) {
      $result[$key] = $contact['first_name'] . ' ' . $contact['last_name'];
    }
    return $result;
  }

  /**
   * Ajax callback for the 'from contact group' selection.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The portion of the render structure that will replace the form element.
   */
  public function fromContactCallback(array $form, FormStateInterface $form_state) {
    return $form['contact']['from_contact_container'];
  }

  /**
   * Ajax callback for the 'validation contacts groups' selection.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The portion of the render structure that will replace the form element.
   */
  public function validationContactsCallback(array $form, FormStateInterface $form_state) {
    return $form['contact']['validation_contacts_container'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'civimail_digest.settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('civimail_digest.settings');

    $availableGroups = $this->getGroups();

    // Do not get from contacts when the group filter is empty
    // as this could fetch all the contacts.
    $fromGroup = [];
    $fromContacts = [];
    if (!empty($form_state->getValue('from_group'))) {
      $fromGroup = $form_state->getValue('from_group');
      $fromContacts = $this->getContacts([$fromGroup]);
    }
    elseif (!empty($config->get('from_group'))) {
      $fromGroup = $config->get('from_group');
      $fromContacts = $this->getContacts([$fromGroup]);
    }

    // Do not get validation contacts when the group filter is empty
    // as this could fetch all the contacts.
    $validationGroups = [];
    $validationContacts = [];
    if (!empty($form_state->getValue('validation_groups'))) {
      $validationGroups = $form_state->getValue('validation_groups');
      // @todo multiple validation groups
      $validationContacts = $this->getContacts([$validationGroups]);
    }
    elseif (!empty($config->get('validation_groups'))) {
      $validationGroups = $config->get('validation_groups');
      // @todo multiple validation groups
      $validationContacts = $this->getContacts([$validationGroups]);
    }

    // @todo dependency injection
    $entityDisplayRepository = \Drupal::service('entity_display.repository');
    // @todo extend to other content entities
    $viewModes = $entityDisplayRepository->getViewModeOptions('node');

    // @todo dependency injection
    $languageManager = \Drupal::languageManager();
    $languages = $languageManager->getLanguages();
    $availableLanguages = [];
    foreach ($languages as $key => $language) {
      $availableLanguages[$key] = $language->getName();
    }

    $form['digest_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Digest title'),
      // @todo use token for digest number.
      '#description' => $this->t('Title that appears in mail subject, and title in browser view. The digest number will be appended.'),
      '#maxlength' => 254,
      '#size' => 64,
      '#required' => TRUE,
      '#default_value' => $config->get('digest_title'),
    ];
    $form['is_active'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Is active'),
      '#description' => $this->t('When checked, digests of the content that was previously sent via CiviMail will be prepared automatically on the selected day and hour, each week.'),
      '#default_value' => $config->get('is_active'),
    ];

    $form['schedule'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Schedule'),
      '#states' => [
        'visible' => [
          ':input[name="is_active"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['schedule']['week_day'] = [
      '#type' => 'select',
      '#title' => $this->t('Week day'),
      '#description' => $this->t('Day to send the weekly digest.'),
      '#options' => $this->getWeekDays(),
      '#required' => TRUE,
      '#default_value' => $config->get('week_day'),
    ];
    $form['schedule']['hour'] = [
      '#type' => 'select',
      '#title' => $this->t('Hour'),
      '#description' => $this->t('Hour to send the weekly digest.'),
      '#options' => $this->getHours(),
      '#required' => TRUE,
      '#default_value' => $config->get('hour'),
    ];

    $form['display'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Display'),
      '#states' => [
        'visible' => [
          ':input[name="is_active"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['display']['view_mode'] = [
      '#type' => 'select',
      '#title' => t('Content view mode'),
      '#options' => $viewModes,
      '#description' => $this->t('View mode that will be used by the digest for each content excerpt.'),
      '#default_value' => $config->get('view_mode'),
    ];

    $form['limit'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Limit'),
      '#states' => [
        'visible' => [
          ':input[name="is_active"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['limit']['quantity_limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Quantity limit'),
      '#description' => $this->t('Limits the amount of entities that will be included in a single digest.'),
      '#required' => TRUE,
      '#default_value' => $config->get('quantity_limit'),
    ];
    $form['limit']['bundles'] = [
      '#type' => 'select',
      '#title' => $this->t('Bundles'),
      '#description' => $this->t('Optionally limit bundles that can be part of the digest. All apply if none selected.'),
      '#options' => $this->getBundles(),
      '#multiple' => TRUE,
      '#required' => TRUE,
      '#default_value' => $config->get('bundles'),
    ];
    $form['limit']['include_update'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include CiviMail updates'),
      '#description' => $this->t('If checked, when several mailings have been sent for the same content it will also include the last one, even if the content has already been included in a previous mailing.'),
      '#default_value' => $config->get('include_update'),
    ];
    $form['limit']['age_in_days'] = [
      '#type' => 'number',
      '#title' => $this->t('Days'),
      '#description' => $this->t('Do not include content older than the defined days.'),
      '#required' => TRUE,
      '#default_value' => $config->get('age_in_days'),
    ];
    // @todo open to multilingual digest
    $form['limit']['language'] = [
      '#type' => 'select',
      '#title' => $this->t('Language'),
      '#description' => $this->t('Include CiviMail mailings in this language.'),
      '#options' => $availableLanguages,
      '#multiple' => FALSE,
      '#required' => TRUE,
      '#default_value' => $config->get('language'),
    ];

    $form['contact'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Contact'),
      '#states' => [
        'visible' => [
          ':input[name="is_active"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // From group and contact dependent select elements.
    $form['contact']['from_group'] = [
      '#type' => 'select',
      '#title' => $this->t('From contact groups'),
      '#description' => $this->t('Set a group that will be used to filter the from contact.'),
      '#options' => $availableGroups,
      '#default_value' => $fromGroup,
      '#ajax' => [
        'callback' => '::fromContactCallback',
        'wrapper' => 'from-contact-container',
        'event' => 'change',
      ],
      '#required' => TRUE,
    ];
    // JS fallback to trigger a form rebuild.
    $form['contact']['choose_from_group'] = [
      '#type' => 'submit',
      '#value' => $this->t('Choose from contact group'),
      '#states' => [
        'visible' => ['body' => ['value' => TRUE]],
      ],
    ];
    $form['contact']['from_contact_container'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'from-contact-container'],
    ];
    $form['contact']['from_contact_container']['from_contact_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Choose a contact'),
    ];
    $form['contact']['from_contact_container']['from_contact_fieldset']['from_contact'] = [
      '#type' => 'select',
      '#title' => $this->t('from contact'),
      '#description' => $this->t('Contact that will be used as the sender.'),
      '#options' => $fromContacts,
      '#default_value' => $config->get('from_contact'),
      '#required' => TRUE,
    ];

    $form['contact']['to_groups'] = [
      '#type' => 'select',
      '#title' => $this->t('To groups'),
      '#description' => $this->t('CiviCRM groups that will receive the digest.'),
      '#options' => $this->getGroups(),
      '#multiple' => TRUE,
      '#required' => TRUE,
      '#default_value' => $config->get('to_groups'),
    ];
    $form['contact']['test_groups'] = [
      '#type' => 'select',
      '#title' => $this->t('Test groups'),
      '#description' => $this->t('CiviCRM groups that will receive tests.'),
      '#options' => $this->getGroups(),
      '#multiple' => TRUE,
      '#default_value' => $config->get('test_groups'),
    ];

    // Validation groups and contacts dependent select elements.
    $form['contact']['validation_groups'] = [
      '#type' => 'select',
      '#title' => $this->t('Validation contact groups'),
      '#description' => $this->t('Set one or multiple groups that will be used to filter the validation contacts.'),
      '#options' => $availableGroups,
      '#default_value' => $validationGroups,
      '#ajax' => [
        'callback' => '::validationContactsCallback',
        'wrapper' => 'validation-contacts-container',
        'event' => 'change',
      ],
      // @todo open to multiple groups
      '#multiple' => FALSE,
      '#required' => TRUE,
    ];
    // JS fallback to trigger a form rebuild.
    $form['contact']['choose_validation_group'] = [
      '#type' => 'submit',
      '#value' => $this->t('Choose validation contact group'),
      '#states' => [
        'visible' => ['body' => ['value' => TRUE]],
      ],
    ];
    $form['contact']['validation_contacts_container'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'validation-contacts-container'],
    ];
    $form['contact']['validation_contacts_container']['validation_contacts_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Choose at least one contact'),
    ];
    $form['contact']['validation_contacts_container']['validation_contacts_fieldset']['validation_contacts'] = [
      '#type' => 'select',
      '#title' => $this->t('Validation contacts'),
      '#description' => $this->t('CiviCRM contacts that will confirm that the digest can be sent.'),
      '#options' => $validationContacts,
      '#default_value' => $config->get('validation_contacts'),
      '#multiple' => TRUE,
      '#required' => TRUE,
    ];

    // If no group is selected for a contact give a hint to the user
    // that it must be selected first.
    if (empty($config->get('from_group')) && empty($form_state->getValue('from_group'))) {
      $form['contact']['from_contact_container']['from_contact_fieldset']['from_contact']['#title'] = $this->t('You must choose the from group first.');
      $form['contact']['from_contact_container']['from_contact_fieldset']['from_contact']['#disabled'] = TRUE;
    }
    if (empty($config->get('validation_groups')) && empty($form_state->getValue('validation_groups'))) {
      $form['contact']['validation_contacts_container']['validation_contacts_fieldset']['validation_contacts']['#title'] = $this->t('You must choose the validation group first.');
      $form['contact']['validation_contacts_container']['validation_contacts_fieldset']['validation_contacts']['#disabled'] = TRUE;
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Make the distinction between plain form submit and ajax trigger.
    $trigger = (string) $form_state->getTriggeringElement()['#value'];
    if ($trigger == 'Save configuration') {
      parent::submitForm($form, $form_state);
      $this->config('civimail_digest.settings')
        ->set('digest_title', $form_state->getValue('digest_title'))
        ->set('is_active', $form_state->getValue('is_active'))
        ->set('week_day', $form_state->getValue('week_day'))
        ->set('hour', $form_state->getValue('hour'))
        ->set('view_mode', $form_state->getValue('view_mode'))
        ->set('quantity_limit', $form_state->getValue('quantity_limit'))
        ->set('bundles', $form_state->getValue('bundles'))
        ->set('include_update', $form_state->getValue('include_update'))
        ->set('age_in_days', $form_state->getValue('age_in_days'))
        ->set('language', $form_state->getValue('language'))
        ->set('from_group', $form_state->getValue('from_group'))
        ->set('from_contact', $form_state->getValue('from_contact'))
        ->set('to_groups', $form_state->getValue('to_groups'))
        ->set('test_groups', $form_state->getValue('test_groups'))
        ->set('validation_groups', $form_state->getValue('validation_groups'))
        ->set('validation_contacts', $form_state->getValue('validation_contacts'))
        ->save();
    }
    else {
      $form_state->setRebuild();
    }
  }

}
