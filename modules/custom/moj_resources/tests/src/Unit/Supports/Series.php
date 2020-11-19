<?php

namespace Drupal\Tests\moj_resources\Unit\Supports;

use Drupal\Tests\moj_resources\Unit\Supports\Term;
use Drupal\Tests\moj_resources\Unit\Supports\TestHelpers;

/**
 * Test Helper for creating mock series
 *
 * @group unit_moj_resources
 */
class Series extends Term {
  private $type;
  private $prison;

  public function __construct($unitTestCase, $nid) {
    parent::__construct($unitTestCase, $nid);
    $this->type = $this->testHelpers->createFieldWith('target_id', 'series');
    $this->prison = $this->testHelpers->createEmptyField();
  }

  /**
   * Create a new instance of Series with a node ID
   *
   * @param string $title
   * @return Term
  */
  static public function createWithNodeId($unitTestCase, $nid) {
    $prisonTerm = new self($unitTestCase, $nid);
    return $prisonTerm;
  }

  /**
   * Add a prison ID
   *
   * @param int $prisonId
   * @return Term
  */
  public function setPromotedPrison($prisonId) {
      $this->prison = $this->testHelpers->createFieldWith('target_id', $prisonId);
      return $this;
  }

  /**
   * Create a returnValueMap object for testing
   *
   * @return array
  */
  public function createReturnValueMap() {
     return array(
        array("nid", $this->nid),
        array("type", $this->type),
        array("title", $this->title),
        array("field_moj_description", $this->description),
        array("field_prison_categories", $this->prisonCategories),
        array("field_promoted_to_prison", $this->prison)
    );
  }
}
