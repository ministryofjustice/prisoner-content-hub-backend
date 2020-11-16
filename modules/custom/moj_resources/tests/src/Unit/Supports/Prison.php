<?php

namespace Drupal\Tests\moj_resources\Unit\Supports;

use Drupal\Tests\moj_resources\Unit\Supports\Term;

/**
 * Test Helper for creating mock prisons
 *
 * @group unit_moj_resources
 */
class Prison extends Term {
  public $type;

  public function __construct($nid) {
    parent::__construct($nid);
    $this->type = (object) array("target_id" => "prisons");
  }

  /**
   * Create a new instance of Prison with a node ID
   *
   * @param string $title
   * @return Term
  */
  static public function createWithNodeId($nid) {
    $prisonTerm = new self($nid);
    return $prisonTerm;
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
        array("field_prison_categories", $this->prisonCategories)
    );
  }
}
