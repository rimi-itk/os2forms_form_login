<?php

namespace Drupal\os2forms_form_login\Helper;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Webform helper.
 */
class WebformHelper {
  use StringTranslationTrait;

  public const MODULE = 'os2forms_form_login';

  /**
   * The login provider helper.
   *
   * @var LoginProviderHelper
   */
  private $loginProviderHelper;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  private $messenger;

  /**
   * Constructor.
   */
  public function __construct(LoginProviderHelper $loginProviderHelper, MessengerInterface $messenger) {
    $this->loginProviderHelper = $loginProviderHelper;
    $this->messenger = $messenger;
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

    $form['third_party_settings'][static::MODULE][static::MODULE][LoginProviderHelper::FORCE_AUTHENTICATION] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Force authenticated users to re-authenticate'),
      '#default_value' => $settings[LoginProviderHelper::FORCE_AUTHENTICATION] ?? NULL,
      '#description' => $this->t('If checked, an authenticated user will be signed out and required to log in before accessing the form.'),
      '#states' => [
        'disabled' => [
          ':input[name="third_party_settings[' . static::MODULE . '][' . static::MODULE . '][' . LoginProviderHelper::PROVIDER_SETTING . ']"]' => ['value' => ''],
        ],
      ],
    ];
  }

  /**
   * Implements hook_webform_submission_form_alter().
   */
  public function submissionFormAlter(array &$form, FormStateInterface $form_state, $form_id) {
    /** @var \Drupal\webform\WebformSubmissionInterface */
    $webformSubmission = $form_state->getFormObject()->getEntity();
    /** @var \Drupal\webform\WebformInterface */
    $webform = $webformSubmission->getWebform();
    $webformLoginProvider = $this->loginProviderHelper->getWebformLoginProvider($webform);

    if (NULL !== $webformLoginProvider) {
      $activeLoginProvider = $this->loginProviderHelper->getActiveLoginProvider();
      $signInRequired = FALSE;

      $location = $this->loginProviderHelper->getCurrentRequestUri();
      if (NULL === $activeLoginProvider) {
        $signInUrl = $this->loginProviderHelper->getLoginUrl($webformLoginProvider, '<current>');
        $this->messenger->addWarning(
          $this->t(
            'You have to sign in to fill out this form. Please <a href="@sign_in_url">sign in and try again<a/>.',
            [
              '@sign_in_url' => $signInUrl,
            ]
          )
        );
        $signInRequired = TRUE;
      }
      elseif ($activeLoginProvider !== $webformLoginProvider) {
        $signOutUrl = Url::fromRoute('user.logout', [], ['query' => ['location' => $location]])->toString();
        $this->messenger->addWarning(
          $this->t(
            'Your current login type does match the login type required by the webform. Please <a href="@sign_out_url">sign out and try again<a/>.',
            [
              '@sign_out_url' => $signOutUrl,
            ]
          )
        );
        $signInRequired = TRUE;
      }

      if ($signInRequired) {
        // Disable all action elements when user is not allowed to submit form.
        foreach (Element::children($form['actions']) as $key) {
          $form['actions'][$key]['#disabled'] = TRUE;
        }
      }
    }
  }

}
