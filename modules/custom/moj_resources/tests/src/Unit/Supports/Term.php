<?php

namespace Drupal\Tests\moj_resources\Unit\Supports;

/**
 * Abstract base class for creating term helpers
 *
 * @group unit_moj_resources
 */
abstract class Term {

  public $nid;
  public $title;
  public $description;
  public $prisonCategories = [];

  public function __construct($nid = 123) {
    $this->nid = (object) array("value" => $nid);
    $this->title = (object) array("value" => "Test Term");
    $this->description = $this->createDescription("This is a test term");
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
     array_push($this->prisonCategories, (object) array("target_id" => $prisonCategoryId));
     return $this;
  }

  /**
   * Create a description object
   *
   * @param string $description
   * @return array
  */
  private function createDescription($description) {
    $description = (object) array(
      "raw" => $description,
      "processed" => $description,
      "summary" => $description,
      "sanitized" => $description
    );

    return array($description);
  }

  /**
   * Create a returnValueMap object for testing
   *
   * @return array
  */
  abstract public function createReturnValueMap();

}
