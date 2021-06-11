<?php

namespace Drupal\os2forms_form_login\Helper;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\itkdev_openid_connect_drupal\Helper\ConfigHelper as OpenIDConnectConfigHelper;
use Drupal\os2forms_form_login\Exception\UnknownLoginProviderException;
use Drupal\webform\WebformInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Login provider helper.
 */
class LoginProviderHelper {
  use StringTranslationTrait;

  public const PROVIDER_SETTING = 'os2forms_form_login_provider';
  public const FORCE_AUTHENTICATION = 'os2forms_form_login_force_relogin';

  /**
   * The OpenID Connect config helper.
   *
   * @var \Drupal\itkdev_openid_connect_drupal\Helper\ConfigHelper
   */
  private $openIDConnectConfigHelper;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  private $requestStack;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  private $currentUser;

  /**
   * Constructor.
   */
  public function __construct(OpenIDConnectConfigHelper $openIDConnectConfigHelper, RequestStack $requestStack, AccountProxyInterface $currentUser) {
    $this->openIDConnectConfigHelper = $openIDConnectConfigHelper;
    $this->requestStack = $requestStack;
    $this->currentUser = $currentUser;
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
   *   Optional location to return to after login. If set to `<current>` the
   *   current request uri will be used.
   *
   * @return string
   *   The login url.
   *
   * @throws \Drupal\os2forms_form_login\Exception\UnknownLoginProviderException
   *   Throws if provider cannot be found.
   */
  public function getLoginUrl(array $provider, string $location = NULL): string {
    $query = [];
    if ($location) {
      if ('<current>' === $location) {
        $location = $this->requestStack->getCurrentRequest()->getRequestUri();
      }
      $query['location'] = $location;
    }

    switch ($provider['module'] ?? NULL) {
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

  /**
   * Get login provider for a webform.
   */
  public function getWebformLoginProvider(WebformInterface $webform): ?array {
    $settings = $webform->getThirdPartySetting(WebformHelper::MODULE, WebformHelper::MODULE);

    return $this->getLoginProvider($settings[static::PROVIDER_SETTING] ?? '');
  }

  /**
   * Get login provider used by the currently authenticated user if any.
   */
  public function getActiveLoginProvider(): ?array {
    if ($this->currentUser->isAnonymous()) {
      return NULL;
    }

    // @todo How do we actually get the current user's authentication provider?
    return $this->getLoginProvider('itkdev_openid_connect_drupal.nemid');

    return NULL;
  }

  /**
   * Implements hook_user_logout().
   */
  public function userLogout(AccountInterface $account) {
    $request = $this->requestStack->getCurrentRequest();
    if (NULL !== $request) {
      $location = $request->get('location');
      if (NULL !== $location) {
        (new RedirectResponse(
          $location
        ))->send();
      }
    }
  }

  /**
   * Get the current request uri.
   */
  public function getCurrentRequestUri() {
    $request = $this->requestStack->getCurrentRequest();
    if (NULL !== $request) {
      return $request->getRequestUri();
    }

    return NULL;
  }

}
