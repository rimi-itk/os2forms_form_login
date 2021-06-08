<?php

namespace Drupal\os2forms_openid_connect\EventSubscriber;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\os2forms_openid_connect\Helper\FormHelper;
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
  public function __construct(KillSwitch $killSwitch, EntityFieldManagerInterface $entityFieldManager, AccountInterface $account) {
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

    $loginMethod = $settings['login_method'];
    if (NULL !== $loginMethod) {
      $this->killSwitch->trigger();

      $loginUrl = Url::fromRoute('itkdev_openid_connect_drupal.openid_connect', ['key' => $loginMethod]);
      $response = new RedirectResponse($loginUrl->toString());
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
