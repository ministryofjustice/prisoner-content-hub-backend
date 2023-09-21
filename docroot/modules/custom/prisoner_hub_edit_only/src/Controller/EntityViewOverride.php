<?php

namespace Drupal\prisoner_hub_edit_only\Controller;

use Drupal\Core\Entity\Controller\EntityViewController;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Controller for redirecting node views to node edits.
 */
class EntityViewOverride extends EntityViewController {

  /**
   * A list of excluded node types, that should not be redirected.
   *
   * @var string[]
   */
  static private array $excludedContentTypes = ['help_page'];

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
    if ($destination = \Drupal::request()->query->get('destination')) {
      $options['query']['destination'] = $destination;
      \Drupal::request()->query->remove('destination');
    }
    $options['absolute'] = TRUE;
    return new RedirectResponse(Url::fromRoute('entity.' . $entity->getEntityTypeId() . '.edit_form', [$entity->getEntityTypeId() => $entity->id()], $options)->toString());
  }

}
