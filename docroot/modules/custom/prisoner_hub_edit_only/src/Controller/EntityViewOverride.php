<?php

namespace Drupal\prisoner_hub_edit_only\Controller;

use Drupal\Core\Entity\Controller\EntityViewController;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Controller for redirecting node views to node edits.
 */
class EntityViewOverride extends EntityViewController {

  /**
   * A list of excluded node types, that should not be redirected.
   *
   * @var string[]
   */
  private static array $excludedContentTypes = ['help_page'];

  /**
   * Creates an EntityViewOverride object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RendererInterface $renderer, protected RequestStack $requestStack) {
    parent::__construct($entity_type_manager, $renderer);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('renderer'),
      $container->get('request_stack'),
    );
  }

  /**
   * The view node handler.
   *
   * This must be in its own function, as Drupal uses a reflector class to
   * extract the variable names, i.e. $node.
   *
   * @param \Drupal\Core\Entity\EntityInterface $node
   *   The node object.
   * @param string $view_mode
   *   The view mode.
   * @param null $langcode
   *   The language code.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse|array
   *   The redirect response, to take the user to the edit page.
   *   Or a render array if the node being viewed is not of a bundle that is
   *   appropriate to redirect, for example, help pages.
   *
   * @see \Drupal\Core\Entity->setParametersFromReflection();
   */
  public function viewNode(EntityInterface $node, $view_mode = 'full', $langcode = NULL) {
    if (in_array($node->bundle(), self::$excludedContentTypes)) {
      return parent::view($node, $view_mode);
    }
    return $this->redirectToEditForm($node);
  }

  /**
   * Redirect users to the edit page.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity object.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   The redirect response.
   */
  protected function redirectToEditForm(EntityInterface $entity) {
    $options = [];
    // Copy of destination parameter if it is in the request.
    if ($destination = $this->requestStack->getCurrentRequest()->query->get('destination')) {
      $options['query']['destination'] = $destination;
      $this->requestStack->getCurrentRequest()->query->remove('destination');
    }
    $options['absolute'] = TRUE;
    return new RedirectResponse(Url::fromRoute('entity.' . $entity->getEntityTypeId() . '.edit_form', [$entity->getEntityTypeId() => $entity->id()], $options)->toString());
  }

}
