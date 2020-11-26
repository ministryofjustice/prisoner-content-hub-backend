<?php

namespace Drupal\Tests\moj_resources\Unit\Supports;

use Drupal\Tests\moj_resources\Unit\Supports\Term;
use Drupal\Tests\moj_resources\Unit\Supports\TestHelpers;

/**
 * Test Helper for creating mock prisons
 *
 * @group unit_moj_resources
 */
class Prison extends Term {
  private $vid;

  public function __construct($unitTestCase, $nid) {
    parent::__construct($unitTestCase, $nid);
    $this->vid = $this->testHelpers->createFieldWith('target_id', 'prisons');
  }

  /**
   * Create a new instance of Prison with a node ID
   *
   * @param string $title
   * @return Term
  */
  static public function createWithNodeId($unitTestCase, $tid) {
    $prisonTerm = new self($unitTestCase, $tid);
    return $prisonTerm;
  }

  /**
   * Create a returnValueMap object for testing
   *
   * @return array
  */
  public function createReturnValueMap() {
     return array(
        array("tid", $this->tid),
        array("vid", $this->vid),
        array("name", $this->name),
        array("field_moj_description", $this->description),
        array("field_prison_categories", $this->prisonCategories)
    );
  }
}
