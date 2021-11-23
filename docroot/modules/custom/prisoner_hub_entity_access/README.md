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
  See https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Entity%21entity.api.php/function/hook_entity_access/9.x
  Implemented in `src/EntityAccessCheck.php`
  This hook only works for when an entity is fully loaded, so should be considered as the "last-resort" for hiding/showing
  content.
- Entity query access API
  Implemented in `src/EventSubscriber/QueryAccessSubscriber.php`
  This API affects all entity queries, which includes any JSON:API response for a list of content.
  E.g. for requests such as `/jsonapi/node/page`.
  Note that without this hook, the results of hook_entity_access will still be respected.  However, because hook_entity_access
  only works _after_ the entity has been fully loaded, it means that they will be processed _after_ the result count.
  I.e. if you were requesting 50 nodes, and 5 of them were restricted, you would end up with a JSON:API response of 45
  nodes.  By using the entity query access API we can be sure that the results will always contain 50 valid nodes.
- hook_search_api_query_alter
  Implemented in `src/SearchApiQueryAlter.php`
  This hook works for search_api queries
  E.g. for requests such as `/jsonapi/index/{{name_of_index}}`
  We use this for the same reason as the entity query access api, to ensure
  our result sets always contain a full set of valid rows.


## Relevant Trello card
https://trello.com/c/JxFpJDkr/1944-determine-and-implement-method-for-passing-the-current-prison-to-drupal
