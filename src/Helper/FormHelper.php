<?php

namespace Drupal\os2forms_openid_connect\Helper;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\itkdev_openid_connect_drupal\Helper\ConfigHelper as OpenIDConnectConfigHelper;

/**
 * Form helper.
 */
class FormHelper {
  use StringTranslationTrait;

  public const MODULE = 'os2forms_openid_connect';

  /**
   * The OpenID Connect config helper.
   *
   * @var \Drupal\itkdev_openid_connect_drupal\Helper\ConfigHelper
   */
  private $openIDConnectConfigHelper;

  /**
   * Constructor.
   */
  public function __construct(OpenIDConnectConfigHelper $openIDConnectConfigHelper) {
    $this->openIDConnectConfigHelper = $openIDConnectConfigHelper;
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
      '#title' => $this->t('OS2Forms OpenID Connect settings'),
      '#open' => TRUE,
    ];

    $authenticators = $this->openIDConnectConfigHelper->getAuthenticators();
    $authenticatorOptions = array_filter(array_map(static function (array $authenticator) {
      return $authenticator['name'] ?? NULL;
    }, $authenticators));

    $form['third_party_settings'][static::MODULE][static::MODULE]['login_method'] = [
      '#type' => 'select',
      '#title' => $this->t('Login type'),
      '#default_value' => $settings['login_method'] ?? NULL,
      '#empty_option' => $this->t('Login not required'),
      '#options' => $authenticatorOptions,
      '#description' => $this->t('Require login using the selected login method.'),
    ];
  }

}
