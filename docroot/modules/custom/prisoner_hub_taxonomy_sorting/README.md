# Prisoner Hub Taxonomy Sorting

This module provides functionality related to sorting content on Taxonomy pages.
Currently, this includes the following features:
* Creates a `series_sort_value` read-only field (i.e not editable via the CMS).
  This stores a calculated value that is used for sorting, based on the series that the content is associated with.
  Either season + episode number, or release date. When content is saved/updated, the value of the field is re-calculated.
  This allows for easy sorting via JSON:API by re-using of the same field.
  Also, for DESC direction, all the values of the field are made negative (i.e. 456 becomes -456),
  this allows for the same sorting direction to be used via JSON:API.  I.e. the sorting will always be ASC.
  Example url: /jsonapi/node/moj_video_item?filter[field_moj_series.meta.drupal_internal__tid]=123&sort=series_sort_value
* Creates a CMS page: "Edit episode order", accessed via a tab when editing a Series or Topic.  This displays a
  list of content in the same order as it would on the frontend, and allows the content to be bulk updated in one place.

