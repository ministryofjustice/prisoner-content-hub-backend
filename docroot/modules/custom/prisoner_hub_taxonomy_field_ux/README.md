# Prisoner Hub Taxonomy Field UX

This module provides UX CMS enhancements relating to Taxonomy fields.
This is based on the Drupal form states API, for more info see https://www.drupal.org/docs/drupal-apis/form-api/conditional-form-fields

The changes are:
* Adds state's for season+episode number and release date fields depending on what sorting has been selected for the
  associated Series.  These include showing/hiding, making required/not required.
* Adds state's to the category field depending on whether the user ticked "this content is not part of any series".
  Again including show/hiding, and required/not required.
