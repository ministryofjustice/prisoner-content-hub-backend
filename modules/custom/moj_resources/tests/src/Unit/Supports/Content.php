<?php

namespace Drupal\Tests\moj_resources\Unit\Supports;

/**
 * Abstract base class for creating content helpers
 *
 * @group unit_moj_resources
 */
abstract class Content {

  public $nid;
  public $title;
  public $description;
  public $season;
  public $episode;
  public $prisons = [];
  public $prisonCategories = [];
  public $series = [];
  public $secondaryTags = [];
  public $categories = [];
  public $image = [];

  public function __construct($nid = 123) {
    array_push($this->image, (object) array("url" => "/foo.jpg"));
    $this->nid = (object) array("value" => $nid);
    $this->title = (object) array("value" => "Test Content");
    $this->description = $this->createDescription("This is some test content");
    $this->season = (object) array("value" => 0);
    $this->episode = (object) array("value" => 0);
  }

  /**
   * Set the title
   *
   * @param string $title
   * @return Content
  */
  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }

  /**
   * Set the season number
   *
   * @param int $season
   * @return Content
  */
  public function setSeason($season) {
    $this->season = (object) array("value" => $season);
    return $this;
  }

  /**
   * Set the episode number
   *
   * @param int $episode
   * @return Content
  */
  public function setEpisode($episode) {
    $this->episode = (object) array("value" => $episode);
    return $this;
  }

  /**
   * Add a prison ID
   *
   * @param int $prisonId
   * @return Content
  */
  public function addPrison($prisonId) {
     array_push($this->prisons, (object) array("target_id" => $prisonId));
     return $this;
  }

  /**
   * Add a prison ID
   *
   * @param int $prisonCategoryId
   * @return Content
  */
  public function addPrisonCategory($prisonCategoryId) {
     array_push($this->prisonCategories, (object) array("target_id" => $prisonCategoryId));
     return $this;
  }

  /**
   * Add a series ID
   *
   * @param int $seriesId
   * @return Content
  */
  public function addSeries($seriesId) {
     array_push($this->series, (object) array("target_id" => $seriesId));
     return $this;
  }

  /**
   * Add a secondary tag ID
   *
   * @param int $secondaryTagId
   * @return Content
  */
  public function addSecondaryTag($secondaryTagId) {
     array_push($this->secondaryTags, (object) array("target_id" => $secondaryTagId));
     return $this;
  }

  /**
   * Add a category ID
   *
   * @param int $categoryId
   * @return Content
  */
  public function addCategory($categoryId) {
     array_push($this->categories, (object) array("target_id" => $categoryId));
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
