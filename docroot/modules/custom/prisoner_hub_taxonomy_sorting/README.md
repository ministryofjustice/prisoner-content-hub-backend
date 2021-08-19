# Prisoner Hub Taxonomy Sorting

This module provides functionality related to sorting content on Taxonomy pages.
Currently, this includes the following features:
* Creates a new `series_sort_value` read-only field (i.e not editable via the CMS).
  This stores a calculated value for sorting based on the series that the content is associated with.
  Either season + episode number, or release date.  This allows for easy sorting via JSON:API by re-using of the same
  field.  Also, for DESC direction, all the values of the field are inverted, so you should always use ASC direction
  when running queries.
  e.g. /jsonapi/node/moj_video_item?filter[field_moj_series.meta.drupal_internal__tid]=123&sort=series_sort_value
* Adds a tab to Series and Secondary tags taxonomy pages that displays a list of content in the same order as it would
  on the frontend.
* Shows/hides season/episode number and release date fields depending on what sorting has been selected for the
  associated Series.

