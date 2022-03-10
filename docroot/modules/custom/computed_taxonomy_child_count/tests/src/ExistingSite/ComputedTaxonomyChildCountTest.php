<?php

namespace Drupal\Tests\computed_taxonomy_child_count\ExistingSite;

use weitzman\DrupalTestTraits\Entity\TaxonomyCreationTrait;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * @group computed_taxonomy_child_count
 */
class ComputedTaxonomyChildCountTest extends ExistingSiteBase {

  use TaxonomyCreationTrait;

  /**
   * Test that correct values are returned for "child_term_count".
   */
  public function testComputedTaxonomyChildCount() {
    $vocab = $this->createVocabulary();
    $parent_1 = $this->createTerm($vocab);
    $parent_2 = $this->createTerm($vocab);

    $child_1 = $this->createTerm($vocab, [
      'parent' => [
        'target_id' => $parent_1->id(),
      ]
    ]);

    $child_2 = $this->createTerm($vocab, [
      'parent' => [
        'target_id' => $parent_1->id(),
      ]
    ]);

    $this->assertEquals($parent_1->get('child_term_count')->getValue()[0]['value'], 2);
    $this->assertEquals($parent_2->get('child_term_count')->getValue()[0]['value'], 0);
  }

}
