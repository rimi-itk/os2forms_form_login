<?php

namespace Drupal\os2forms_form_login\Helper;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Form helper.
 */
class FormHelper {
  use StringTranslationTrait;

  public const MODULE = 'os2forms_form_login';

  /**
   * The login provider helper.
   *
   * @var LoginProviderHelper
   */
  private $loginProviderHelper;

  /**
   * Constructor.
   */
  public function __construct(LoginProviderHelper $loginProviderHelper) {
    $this->loginProviderHelper = $loginProviderHelper;
  }

  /**
   * Implements hook_webform_third_party_settings_form_alter().
   */
  public function webformThirdPartySettingsFormAlter(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\webform\WebformInterface $webform */
    $webform = $form_state->getFormObject()->getEntity();
    $settings = $webform->getThirdPartySetting(static::MODULE, static::MODULE);

    // OS2Forms NemID.
    $form['third_party_settings'][static::MODULE][static::MODULE] = [
      '#type' => 'details',
      '#title' => $this->t('OS2Forms Form login settings'),
      '#open' => TRUE,
    ];

    $loginProviders = $this->loginProviderHelper->getLoginProviders();
    $loginProvidersOptions = array_filter(array_map(static function (array $provider) {
      return $provider['name'] ?? NULL;
    }, $loginProviders));

    $form['third_party_settings'][static::MODULE][static::MODULE][LoginProviderHelper::PROVIDER_SETTING] = [
      '#type' => 'select',
      '#title' => $this->t('Login type'),
      '#default_value' => $settings[LoginProviderHelper::PROVIDER_SETTING] ?? NULL,
      '#empty_option' => $this->t('Login not required'),
      '#options' => $loginProvidersOptions,
      '#description' => $this->t('Require login using the selected login provider.'),
    ];
  }

}
