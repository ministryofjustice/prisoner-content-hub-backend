# Prisoner hub sub terms

Provides a custom JSON:API resource to return "sub terms" of the given category.

Example url:
`/jsonapi/taxonomy_term/moj_categories/{uuid}/sub_terms`

Note that the above url will work with a prison context.  E.g.
`/jsonapi/prison/{prison name}}/taxonomy_term/moj_categories/{uuid}/sub_terms`

The results will be a paginated list of series and sub-categories.
- Any sub category of the {uuid} (only direct sub-categories are included, not further levels)
- Any series assigned to the {uuid} category (series assigned to sub-categories are not included).

The results will be ordered by the most recently published content within each sub term.
When checking for the most recently updated content, we look at all levels of the taxonomy.  So changes to content that
are in a sub-category multiple levels down, will affect the sorting order of it's parent sub-category (although this
can take up to 24 hours to appear, see the cache tags info below).

Note that if using a prison context, this again will be applied to the results.  So only content available in the
current prison will be used for sorting.

## Custom cache tags
This module uses custom cache tags to invalidate the response when content has been published.
The cache tag looks like:
`'prisoner_hub_sub_terms:123`
(Where 123 is the taxonomy term id of the category).

When content is published, the cache tags for it's associated parent category will be invalidated.
If the content is in a series, the category of the series will be invalidated.
If the content is not in a series, then the category it is associated with will have its parent category invalidated.

There is also a 24 hour max-age set, which means that the sorting for parent categories higher up the hierarchy will
eventually be updated with the new sorting order.
