# Prisoner hub sub terms

Provides a custom JSON:API resource to return "sub terms" of the given category.

Example url:
`/jsonapi/taxonomy_term/moj_categories/{uuid}/sub_terms`

Note that the above url will work with a prison context.  E.g.
`/jsonapi/prison/{prison name}}/taxonomy_term/moj_categories/{uuid}/sub_terms`

The results will be a paginated list of series and sub-categories.
- Any sub category of the {uuid} (only direct sub-categories are included, not further levels)
- Any series assigned to the {uuid} category (series assigned to sub-categories are not included).

The results will be ordered by the most recently updated content within each sub term.
When checking for the most recently updated content, we look at all levels of the taxonomy.  So changes to content that
is in a sub-category multiple levels down, will affect the sorting order of it's parent sub-category.

Note that if using a prison context, this again will be applied to the results.  So only content available in the
current prison will be used for sorting.

## Custom cachetags
This module uses custom cachetags to invalidate the response when content has been updated or created.
The cachetag looks like:
`'prisoner_hub_sub_terms:123`
(Where 123 is the taxonomy term id of the category).

When content is updated or created, the cachetags for it's associated category and parent categories will be invalidated.

The reason for using a custom cachetag instead of the `taxonomy_term:123` tag that comes with Drupal, is that clearing
the more generic Drupal cachetag will affect multiple parts of the site, whereas our custom cachetag can be specific
to sub_terms JSON:API resource.
