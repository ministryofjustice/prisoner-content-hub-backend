<?php

namespace Drupal\prisoner_hub_edit_only\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;

class EntityViewOverride extends ControllerBase {

  /**
   * The view node handler.
   *
   * This must be in its own function, as Drupal uses a reflector class to
   * extract the variable names, i.e. $node.
   * @see \Drupal\Core\Entity->setParametersFromReflection();
   *
   * @param \Drupal\Core\Entity\EntityInterface $node
   *   The node object.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   The redirect response, to take the user to the edit page.
   */
  public function viewNode(EntityInterface $node) {
    return $this->view($node);
  }

  /**
   * The view term handler.
   *
   * This must be in its own function, as Drupal uses a reflector class to
   * extract the variable names, i.e. $taxonomy_term.
   * @see \Drupal\Core\Entity->setParametersFromReflection();
   *
   * @param \Drupal\Core\Entity\EntityInterface $taxonomy_term
   *   The taxonomy term object.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   The redirect response, to take the user to the edit page.
   */
  public function viewTerm(EntityInterface $taxonomy_term) {
    return $this->view($taxonomy_term);
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
  protected function view(EntityInterface $entity) {
    $options = [];
    // Copy of destination parameter if it is in the request.
    if ($destination = \Drupal::request()->query->get('destination')) {
      $options['query']['destination'] = $destination;
      \Drupal::request()->query->remove('destination');
    }
    return $this->redirect('entity.' . $entity->getEntityTypeId() . '.edit_form', [$entity->getEntityTypeId() => $entity->id()], $options);
  }
}
