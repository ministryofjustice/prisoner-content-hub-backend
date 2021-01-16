<?php

namespace Drupal\custom_action_prison_tagging\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Messenger\Messenger;

/**
 * create custom action
 *
 * @Action(
 *   id = "node_prison_tag_action",
 *   label = @Translation("Tag content with prisons"),
 *   type = "node"
 * )
 */
class NodePrisonTagAction extends ActionBase {

    private $prisonIds = [792,959,793]; // TODO: Pull from Taxonomy

    /**
     * {@inheritdoc}
     */
    public function execute($node = NULL) {
        if ($node) {
            $this->updatePrisonEntityReference($node);
        }
    }
    /**
     * {@inheritdoc}
     */
    private function updatePrisonEntityReference($node) {
        if($this->isPrisonReferanceEmpty($node)) {
            $this->setPrisons($node);
        } else {
            \Drupal::messenger()->addError('prisons are already set');
        }
    }
    /**
     * {@inheritdoc}
     */
    private function setPrisons($node) {
        foreach($this->prisonIds as $index => $fid) {
            $node->field_moj_prisons[] = ['target_id' => $fid];
            $node->save();
            \Drupal::messenger()->addStatus('prisons updated');
        }
    }
    
    /**
     * {@inheritdoc}
     */
    private function isPrisonReferanceEmpty($node) {
        return $node->get('field_moj_prisons')->isEmpty() ? TRUE : FALSE;
    }

        /**
     * {@inheritdoc}
     */
    public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
        /** @var \Drupal\node\NodeInterface $object */
        // TODO: write permissions
        $result = $object->access('create', $account, TRUE);
        return $return_as_object ? $result : $result->isAllowed();
    }
}