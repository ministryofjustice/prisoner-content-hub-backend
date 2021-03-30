<?php

namespace Drupal\Tests\moj_resources\Unit\Supports;

use Drupal\Tests\moj_resources\Unit\Supports\Content;

/**
 * Test Helper for creating Audio Items
 *
 * @group unit_moj_resources
 */
class AudioContent extends Content {
  public $type;
  public $duration;
  public $audio = [];

  public function __construct($nid) {
    parent::__construct($nid);
    array_push($this->audio, (object) array("url" => "/foo.mp3"));
    $this->type = (object) array("target_id" => "moj_radio_item");
    $this->duration = (object) array("value" => 60);
  }

  /**
   * Create a new instance of VideoContent with a node ID
   *
   * @param string $title
   * @return Content
  */
  static public function createWithNodeId($nid) {
    $audioContent = new self($nid);
    return $audioContent;
  }

  /**
   * Create a returnValueMap object for testing
   *
   * @return array
  */
  public function createReturnValueMap() {
     return array(
        array("field_moj_season", $this->season),
        array("field_moj_episode", $this->episode),
        array("nid", $this->nid),
        array("type", $this->type),
        array("title", $this->title),
        array("field_moj_thumbnail_image", $this->image),
        array("field_moj_description", $this->description),
        array("field_moj_top_level_categories", $this->categories),
        array("field_moj_secondary_tags", $this->secondaryTags),
        array("field_moj_prisons", $this->prisons),
        array("field_moj_audio", $this->audio),
    );
  }
}
