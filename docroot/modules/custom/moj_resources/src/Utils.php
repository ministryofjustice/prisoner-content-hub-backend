<?php
function getPrisonResults($prison_id, $results) {
  if ($prison_id > 0) {
    $prison_results = $results
      ->orConditionGroup()
      ->condition('field_moj_prisons', $prison_id, '=')
      ->condition('field_moj_prisons', '', '=')
      ->notExists('field_moj_prisons');
    $results->condition($prison_results);
  }


  return $results;
}
?>
