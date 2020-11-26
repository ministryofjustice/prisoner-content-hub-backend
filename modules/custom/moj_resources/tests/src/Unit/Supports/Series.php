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
  private $vid;
  private $prison;
  private $summary;
  private $featuredImage;
  private $featuredAudio;
  private $featuredVideo;
  private $programmeCode;

  public function __construct($unitTestCase, $tid) {
    parent::__construct($unitTestCase, $tid);
    $this->vid = $this->testHelpers->createFieldWith('target_id', 'series');
    $this->prison = $this->testHelpers->createEmptyField();
    $this->summary = $this->testHelpers->createFieldWith('value', 'Content summary');
    $this->featuredImage = $this->testHelpers->createFieldWith('url', '/foo.jpg');
    $this->featuredAudio = $this->testHelpers->createFieldWith('url', '/foo.mp3');
    $this->featuredVideo = $this->testHelpers->createFieldWith('url', '/foo.mp4');
    $this->programmeCode = $this->testHelpers->createFieldWith('value', 'AB123');
  }

  /**
   * Create a new instance of Series with a node ID
   *
   * @param string $title
   * @return Term
  */
  static public function createWithNodeId($unitTestCase, $tid) {
    $seriesTerm = new self($unitTestCase, $tid);
    return $seriesTerm;
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
        array('tid', $this->tid),
        array('vid', $this->vid),
        array('name', $this->name),
        array('description', $this->description),
        array('field_content_summary', $this->summary),
        array('field_prison_categories', $this->prisonCategories),
        array('field_promoted_to_prison', $this->prison),
        array('field_featured_image', $this->featuredImage),
        array('field_featured_audio', $this->featuredAudio),
        array('field_featured_video', $this->featuredVideo),
        array('field_feature_programme_code', $this->programmeCode)
    );
  }
}
