<?php
function getPrisonResults($prison_id, $results) {
  $prison_ids = [
    792, // berwyn
    793, // wayland
    959  // cookham wood
  ];

  if (in_array($prison_id, $prison_ids)) {
    $prison_results = $results
      ->orConditionGroup()
      ->condition('field_moj_prisons', $prison_id, '=')
      ->condition('field_moj_prisons', '', '=')
      ->notExists('field_moj_prisons');
    $results->condition($prison_results);
  }

  return $results;
}

function filterByPrisonTypes($prison_types, $query) {
  $prison_type_or_group = $query
    ->orConditionGroup()
    ->condition('field_prison_types', $prison_types, 'IN')
    ->condition('field_prison_types', NULL, 'IS NULL')
    ->notExists('field_prison_types');

  $query->condition($prison_type_or_group);

  return $query;
}
?>
