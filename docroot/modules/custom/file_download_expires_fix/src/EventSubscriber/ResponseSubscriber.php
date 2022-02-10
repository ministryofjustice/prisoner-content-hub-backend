<?php

namespace Drupal\file_download_expires_fix\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Subscribe to response events.
 */
class ResponseSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[KernelEvents::RESPONSE][] = ['onResponse'];
    return $events;
  }

  /**
   * Remove Cache-Control and Expires headers on cacheable files.
   *
   * By removing the headers, we allow mod_expires to set cache headers from
   * Drupal's htaccess.
   * @see https://www.drupal.org/project/drupal/issues/3263593
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   Response event.
   */
  public function onResponse(ResponseEvent $event): void {
    $response = $event->getResponse();
    if ($response instanceof BinaryFileResponse && $response->isCacheable()) {
      $response->headers->remove('Cache-Control');
      $response->headers->remove('Expires');
    }
  }
}
