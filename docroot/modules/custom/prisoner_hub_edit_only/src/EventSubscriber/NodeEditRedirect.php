<?php

namespace Drupal\entity_edit_replace_view\EventSubscriber;

use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\KernelEvents;

class NodeEditRedirect implements EventSubscriberInterface {

  /**
   * The request stack service.
   *
   * @var RequestStack
   */
  protected $requestStack;

  public function __construct(RequestStack $request_stack) {
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['nodeEditRedirect', 99];
    return $events;
  }

  public function nodeEditRedirect() {
    return;
    $rn = \Drupal::routeMatch()->getRouteName();
    $tsopt = 1;
    if (\Drupal::routeMatch()->getRouteName() == 'entity.node.canonical') {
      $entity_id = \Drupal::routeMatch()->getParameter('node')->id();
      $options = [];
      if ($destination = $this->requestStack->getCurrentRequest()->get('destination')) {
        $options['query']['destination'] = $destination;
      }
      $url = Url::fromRoute('entity.node.edit_form', ['node' => $entity_id], $options)->toString();
      $response = new RedirectResponse($url, 302);
      $response->send();
    }
  }

}
