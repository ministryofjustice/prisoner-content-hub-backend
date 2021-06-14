# Prisoner Hub Entity Access

This module provides entity access rules to show/hide content based on the current prison.
It uses the values of the "Prison" (field_moj_prison) and "Prison Categories" (field_prison_categories),
which are checked to see if they match  the current prison (which is set from the prisoner_hub_prison_context module.)

## Dependencies
- prisoner_hub_prison_context:
  Provides the current prison as a url paramater.
- entity:
  A contrib module that provides the entity query access api, see https://www.drupal.org/node/3002038
- search_api:
  A contrib module that helps us integrate with elasticsearch.

## Why the entity access API?
There are several APIs in Drupal for implementing entity access, the others were dismissed for
the following reasons:
- hook_entity_access():
  This is probably the simplest, but it depends on the entity being fully loaded.  This is
  not the case for some jsonapi responses.
- hook_node_access_records()
  This method setups up a new node_access_records db table, which is essentially a pre-calculated
  map of access.  We _could_ have used this to store the access result for each node/prison.
  However, this would have involved storing this as an additional set of data, that could become
  out of sync.  Also this hook only supports nodes and not other entity types (such as taxonomy).

## Relevant Trello card
https://trello.com/c/JxFpJDkr/1944-determine-and-implement-method-for-passing-the-current-prison-to-drupal

## A note on Search API
Search API queries are altered in a _very_ similar way to entity access API, although it should be noted that the two
are entirely separate API's and manage content stored in separate places.
