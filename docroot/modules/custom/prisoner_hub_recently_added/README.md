# Prisoner hub recently added

Provides a custom JSON:API resource to return recently added content and series.

Example url:
`/jsonapi/recently-added`

This returns a mixture of nodes and taxonomy terms.

The order is based on the most recently published content (via the publication_date module).
For content that is in a series, the series taxonomy entity is returned instead of the node.
For content not in a series, the content entity is returned.

Content that is in the same series is de-duped, so that only one instance of each series entity will appear.

Note this resource does not currently support pagination, but does support a limit.  I.e.
`/jsonapi/recently-added?page[limit]=4`

## Custom cache tags
As the cache for the recently added resource will need to be regularly updated, custom cache tags are used to invalidate
the cache when new published content is created.
(This avoids having to use a more generic cache tag such as `node_list`, which clears on any node CRUD operation).
The cache tags also include the prison context, so that only updates relevant to a specific prison are invalidated.

Example of cache tags used by this module:
`prisoner_hub_recently_added:wayland`

This cache tag is invalidated whenever:
- New content is published to Wayland
- Existing content, that is published to Wayland, is updated,
  and a change has been made to the published date
  (normally this would be content that was previously unpublished and is now published,
  but this could also include a manual update to the published date).

The custom cache tag only takes care of potential new content.
For updates/deletions to existing content previously outputted in the resource, Drupal's standard cache tags take care
of.  E.g. `node:1234`



