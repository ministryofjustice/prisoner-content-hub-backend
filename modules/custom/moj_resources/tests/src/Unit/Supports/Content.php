<?php

namespace Drupal\Tests\moj_resources\Unit\Supports;

use Drupal\Tests\moj_resources\Unit\Supports\TestHelpers;

/**
 * Abstract base class for creating content helpers
 *
 * @group unit_moj_resources
 */
abstract class Content {
  protected $testHelpers;
  protected $nid;
  protected $title;
  protected $description;
  protected $season;
  protected $episode;
  protected $prisons = [];
  protected $prisonCategories = [];
  protected $series = [];
  protected $secondaryTags = [];
  protected $categories = [];
  protected $image = [];

  public function __construct($unitTestCase, $nid = 123) {
    $this->testHelpers = new TestHelpers($unitTestCase);
    array_push($this->image, $this->testHelpers->createFieldWith('url', '/foo.jpg'));
    $this->nid = $this->testHelpers->createFieldWith('value', $nid);
    $this->title = $this->testHelpers->createFieldWith('value', 'Test Content');
    $this->season = $this->testHelpers->createFieldWith('value', 0);
    $this->episode = $this->testHelpers->createFieldWith('value', 0);
    $this->description = $this->testHelpers->createDescriptionField("This is some test content");
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
    $this->season = $this->testHelpers->createFieldWith('value', $season);
    return $this;
  }

  /**
   * Set the episode number
   *
   * @param int $episode
   * @return Content
  */
  public function setEpisode($episode) {
    $this->episode = $this->testHelpers->createFieldWith('value', $episode);
    return $this;
  }

  /**
   * Add a prison ID
   *
   * @param int $prisonId
   * @return Content
  */
  public function addPrison($prisonId) {
     array_push($this->prisons, $this->testHelpers->createFieldWith('target_id', $prisonId));
     return $this;
  }

  /**
   * Add a prison ID
   *
   * @param int $prisonCategoryId
   * @return Content
  */
  public function addPrisonCategory($prisonCategoryId) {
     array_push($this->prisonCategories, $this->testHelpers->createFieldWith('target_id', $prisonCategoryId));
     return $this;
  }

  /**
   * Add a series ID
   *
   * @param int $seriesId
   * @return Content
  */
  public function addSeries($seriesId) {
     array_push($this->series, $this->testHelpers->createFieldWith('target_id', $seriesId));
     return $this;
  }

  /**
   * Add a secondary tag ID
   *
   * @param int $secondaryTagId
   * @return Content
  */
  public function addSecondaryTag($secondaryTagId) {
     array_push($this->secondaryTags, $this->testHelpers->createFieldWith('target_id', $secondaryTagId));
     return $this;
  }

  /**
   * Add a category ID
   *
   * @param int $categoryId
   * @return Content
  */
  public function addCategory($categoryId) {
     array_push($this->categories, $this->testHelpers->createFieldWith('target_id', $categoryId));
     return $this;
  }

  /**
   * Create a returnValueMap object for testing
   *
   * @return array
  */
  abstract public function createReturnValueMap();

}
