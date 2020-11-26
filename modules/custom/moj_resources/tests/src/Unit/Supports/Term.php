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
  protected $tid;
  protected $name;
  protected $description;
  protected $prisonCategories = [];

  public function __construct($unitTestCase, $tid = 123) {
    $this->testHelpers = new TestHelpers($unitTestCase);
    $this->tid = $this->testHelpers->createFieldWith('value', $tid);
    $this->name = $this->testHelpers->createFieldWith('value', 'Test Term');
    $this->description = $this->testHelpers->createDescriptionField("This is a test term");
  }

  /**
   * Set the title
   *
   * @param string $title
   * @return Term
  */
  public function setTitle($name) {
    $this->name = $name;
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
