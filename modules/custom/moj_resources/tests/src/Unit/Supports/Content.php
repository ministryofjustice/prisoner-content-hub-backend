<?php

namespace Drupal\Tests\moj_resources\Unit\Supports;

abstract class Content {
  public $nid;
  public $title;
  public $description;
  public $season;
  public $episode;
  public $prisons = [];
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

  public function setSeason($season) {
    $this->season = (object) array("value" => $season);
    return $this;
  }

  public function setEpisode($episode) {
    $this->episode = (object) array("value" => $episode);
    return $this;
  }

  public function addPrison($prisonId) {
     array_push($this->prisons, (object) array("target_id" => $prisonId));
     return $this;
  }
  public function addSeries($seriesId) {
     array_push($this->series, (object) array("target_id" => $seriesId));
     return $this;
  }
  public function addSecondaryTag($secondaryTagId) {
     array_push($this->secondaryTags, (object) array("target_id" => $secondaryTagId));
     return $this;
  }
  public function addCategory($categoryId) {
     array_push($this->categories, (object) array("target_id" => $categoryId));
     return $this;
  }

  private function createDescription($description) {
    $description = (object) array(
      "raw" => $description,
      "processed" => $description,
      "summary" => $description,
      "sanitized" => $description
    );

    return array($description);
  }

  abstract public function createReturnValueMap();
  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }
}
