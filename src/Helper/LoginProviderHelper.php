<?php

namespace Drupal\os2forms_form_login\Helper;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
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
   * The authorization managers.
   *
   * @var array
   */
  private $authorizationManagers;

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
  public function __construct(array $authorizationManagers, RequestStack $requestStack, AccountProxyInterface $currentUser) {
    $this->authorizationManagers = $authorizationManagers;
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

    foreach ($this->authorizationManagers as $authorizationManager) {
      $providers[] = array_values($authorizationManager->getAuthenticators());
    }

    // Flatten.
    $providers = array_merge(...$providers);

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
  public function getAuthenticationUrl(array $provider, string $location = NULL): string {
    $query = [];
    if ($location) {
      if ('<current>' === $location) {
        $location = $this->requestStack->getCurrentRequest()->getRequestUri();
      }
      $query['location'] = $location;
    }

    foreach ($this->authorizationManagers as $authorizationManager) {
      if (NULL !== ($url = $authorizationManager->getAuthenticationUrl($provider, $query))) {
        break;
      }
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

    // @todo handle multiple authorization managers.
    $provider = $this->authorizationManagers->getActiveLoginProvider();
    if (NULL !== $provider) {
      return $provider;
    }

    return NULL;
  }

  /**
   * Check if current user is authenticated by a provider.
   */
  public function isAuthenticatedByProvider($provider): bool {
    if (!$this->isAuthenticated()) {
      return FALSE;
    }

    if (is_array($provider)) {
      $provider = $provider['id'] ?? NULL;
    }

    foreach ($this->authorizationManagers as $authorizationManager) {
      if ($authorizationManager->isAuthorizedByProvider($provider)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Check if current user is authenticated.
   */
  public function isAuthenticated() {
    return $this->currentUser->isAuthenticated();
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
