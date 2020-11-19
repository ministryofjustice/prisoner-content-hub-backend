<?php

namespace Drupal\Tests\moj_resources\Unit\Supports;

use Drupal\Tests\moj_resources\Unit\Supports\TestHelpers;

/**
 * Abstract base class for creating term helpers
 *
 * @group unit_moj_resources
 */
abstract class Term {
  protected $testHelpers;
  protected $nid;
  protected $title;
  protected $description;
  protected $prisonCategories = [];

  public function __construct($unitTestCase, $nid = 123) {
    $this->testHelpers = new TestHelpers($unitTestCase);
    $this->nid = $this->testHelpers->createFieldWith('value', $nid);
    $this->title = $this->testHelpers->createFieldWith('Test Term', $nid);
    $this->description = $this->testHelpers->createDescriptionField("This is a test term");
  }

  /**
   * Set the title
   *
   * @param string $title
   * @return Term
  */
  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }

  /**
   * Add a prison ID
   *
   * @param int $prisonId
   * @return Term
  */
  public function addPrisonCategory($prisonCategoryId) {
     array_push($this->prisonCategories, $this->testHelpers->createFieldWith('target_id', $prisonCategoryId));
     return $this;
  }

  /**
   * Create a returnValueMap object for testing
   *
   * @return array
  */
  abstract public function createReturnValueMap();

}
