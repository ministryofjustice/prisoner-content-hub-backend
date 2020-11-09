<?php

namespace Drupal\Tests\moj_resources\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\Tests\moj_resources\Unit\TestHelpers;
use Drupal\moj_resources\SeriesContentApiClass;

/**
 * Series Content API Unit tests
 *
 * @group unit_moj_resources
 */

class SeriesContentApiClassTest extends UnitTestCase
{
    /**
     * @var \Drupal\moj_resources\SeriesContentApiClass
     */
    public $seriesContentApiClass;

    public $entityManager;

    public $entityQuery;

    public $entityQueryFactory;

    public $node;

    public $nodeStorage;

    public $node_title;

    public function setUp()
    {
        parent::setUp();

        $this->node = TestHelpers::createMockNode($this);
        $this->nodeStorage = TestHelpers::createMockNodeStorage($this, array($this->node));
        $this->entityManager = TestHelpers::createMockEntityManager(
          $this,
          array( // Refactor this to return different NodeStorage objects
            array("node", $this->nodeStorage),
            array("taxonomy_term", $this->nodeStorage)
          ));
        $this->entityQueryFactory = TestHelpers::createMockQueryFactory($this, array(1234 => 1234));

        $this->seriesContentApiClass = new SeriesContentApiClass($this->entityManager, $this->entityQueryFactory);
    }

    public function testGetSeriesContentNodeIds()
    {
      $series = $this->seriesContentApiClass->SeriesContentApiEndpoint(
        "en/GB",
        123,
        null,
        null,
        456,
        "ASC"
      );

      $this->assertEquals(count($series), 1);
    }
}
