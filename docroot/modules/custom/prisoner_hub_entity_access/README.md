# Prisoner Hub Entity Access

This module provides entity access rules to show/hide content based on the current prison.
It uses the values of the "Prison" (field_moj_prison) and "Prison Categories" (field_prison_categories),
which are checked to see if they match the current prison (which is set from the prisoner_hub_prison_context module).

## Dependencies
- prisoner_hub_prison_context:
  Provides the current prison as a url parameter.
- entity:
  A contrib module that provides the entity query access api, see https://www.drupal.org/node/3002038
- search_api:
  A contrib module that helps us integrate with elasticsearch.

## Why the different entity access APIs?
Drupal offers several API's for hiding/restricting content.
Below are the ones used by this module.
- hook_entity_access
  Implemented in `src/EntityAccessCheck.php`
  This hook only works for when an entity is fully loaded, and this (normally) only happens when viewing the entity directly.
  I.e. for requests such as `/jsonapi/node/page/{{uuid}}`
- Entity query access api
  Implemented in `src/EventSubscriber/QueryAccessSubscriber.php`
  This API affects all entity queries, which includes any JSON:API response for a list of content.
  I.e. for requests such as `/jsonapi/node/page`
- hook_search_api_query_alter
  Implemented in `src/SearchApiQueryAlter.php`
  This hook works for search_api queries.
  I.e. for requests such as `/jsonapi/index/{{name_of_index}}`

## Relevant Trello card
https://trello.com/c/JxFpJDkr/1944-determine-and-implement-method-for-passing-the-current-prison-to-drupal
