<?php

namespace Drupal\os2forms_form_login\Helper;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\itkdev_openid_connect_drupal\Helper\ConfigHelper as OpenIDConnectConfigHelper;
use Drupal\os2forms_form_login\Exception\UnknownLoginProviderException;

/**
 * Login provider helper.
 */
class LoginProviderHelper {
  use StringTranslationTrait;

  public const PROVIDER_SETTING = 'os2forms_form_login_provider';

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
   * Get all enabled login providers.
   *
   * @return array
   *   The providers.
   */
  public function getLoginProviders(): array {
    $providers = [];

    $authenticators = $this->openIDConnectConfigHelper->getAuthenticators();
    foreach ($authenticators as $key => &$authenticator) {
      $authenticator['module'] = 'itkdev_openid_connect_drupal';
      $authenticator['key'] = $key;
    }
    $providers[] = $authenticators;

    // Flatten and add provider ids.
    $providers = array_merge(...$providers);
    foreach ($providers as &$provider) {
      $provider['id'] = $provider['module'] . '.' . $provider['key'];
    }

    return array_column($providers, NULL, 'id');
  }

  /**
   * Get a login providers.
   *
   * @param string $id
   *   The provider id.
   *
   * @return array|null
   *   The provider.
   */
  public function getLoginProvider(string $id): ?array {
    return $this->getLoginProviders()[$id] ?? NULL;
  }

  /**
   * Get provider login url.
   *
   * @param array $provider
   *   The provider.
   * @param string $location
   *   Optional location to return to after login.
   *
   * @return string
   *   The login url.
   *
   * @throws \Drupal\os2forms_form_login\Exception\UnknownLoginProviderException
   *   Throws if provider cannot be found.
   */
  public function getLoginUrl(array $provider, string $location = NULL): string {
    $query = ['location' => $location];

    switch ($provider['module']) {
      case 'itkdev_openid_connect_drupal':
        $url = Url::fromRoute('itkdev_openid_connect_drupal.openid_connect', $query + ['key' => $provider['key']]);
        break;

      default:
        throw new UnknownLoginProviderException();
    }

    if (!isset($url)) {
      throw new UnknownLoginProviderException();
    }

    return $url->toString();
  }

}
