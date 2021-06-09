<?php

namespace Drupal\os2forms_form_login\EventSubscriber;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Session\AccountInterface;
use Drupal\os2forms_form_login\Helper\FormHelper;
use Drupal\os2forms_form_login\Helper\LoginProviderHelper;
use Drupal\webform\WebformInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Login redirect subscriber.
 */
class LoginRedirectSubscriber implements EventSubscriberInterface {
  /**
   * The login provider helper.
   *
   * @var \Drupal\os2forms_form_login\Helper\LoginProviderHelper
   */
  private $loginProviderHelper;

  /**
   * The kill switch.
   *
   * @var \Drupal\Core\PageCache\ResponsePolicy\KillSwitch
   */
  private $killSwitch;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  private $entityFieldManager;

  /**
   * The account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  private $account;

  /**
   * Constructor.
   */
  public function __construct(LoginProviderHelper $loginProviderHelper, KillSwitch $killSwitch, EntityFieldManagerInterface $entityFieldManager, AccountInterface $account) {
    $this->loginProviderHelper = $loginProviderHelper;
    $this->killSwitch = $killSwitch;
    $this->entityFieldManager = $entityFieldManager;
    $this->account = $account;
  }

  /**
   * Redirects to authentication url if needed.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   The subscribed event.
   */
  public function redirectToLogin(GetResponseEvent $event) {
    $request = $event->getRequest();
    $webform = $this->getWebform($request);

    if (NULL === $webform) {
      return;
    }

    $settings = $webform->getThirdPartySetting(FormHelper::MODULE, FormHelper::MODULE);

    $id = $settings[LoginProviderHelper::PROVIDER_SETTING];
    $provider = $this->loginProviderHelper->getLoginProvider($id);
    if (NULL !== $provider) {
      // @todo Check if account is authenticated with provider.
      if ($this->account->isAuthenticated()) {
        return;
      }

      $loginUrl = $this->loginProviderHelper->getLoginUrl($provider, $request->getRequestUri());

      $this->killSwitch->trigger();
      $response = new RedirectResponse($loginUrl);
      $event->setResponse($response);
      $event->stopPropagation();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      KernelEvents::REQUEST => ['redirectToLogin'],
    ];
  }

  /**
   * Get webform for request if any.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Drupal\webform\WebformInterface|null
   *   The webform.
   */
  private function getWebform(Request $request): ?WebformInterface {
    $route = $request->attributes->get('_route');

    switch ($route) {
      case 'entity.webform.canonical':
        return $request->attributes->get('webform');

      case 'entity.node.canonical':
        $node = $request->attributes->get('node');
        $nodeType = $node->getType();

        // Search if this node type is related with field of type 'webform'.
        $webformFieldMap = $this->entityFieldManager->getFieldMapByFieldType('webform');
        if (isset($webformFieldMap['node'])) {
          foreach ($webformFieldMap['node'] as $field_name => $field_meta) {
            // We found field of type 'webform' in this node, let's try fetching
            // the webform.
            if (in_array($nodeType, $field_meta['bundles'], TRUE)) {
              $entity = $node->get($field_name)->referencedEntities()[0] ?? NULL;
              if ($entity instanceof WebformInterface) {
                return $entity;
              }
            }
          }
        }
    }

    return NULL;
  }

}
