# Prisoner hub sub terms

Provides a custom JSON:API resource to return "sub terms" of the given category.

Example url:
`/jsonapi/taxonomy_term/moj_categories/{uuid}/sub_terms`

Note that the above url will work with a prison context.  E.g.
`/jsonapi/prison/{prison name}}/taxonomy_term/moj_categories/{uuid}/sub_terms`

The results will be a paginated list of series and sub-categories.
- Any sub category of the {uuid} (including multiple levels, so sub-sub-sub-categories will also work)
- Any series assigned to either the {uuid} category, or any of it's sub-categories (again on multiple levels).

The results will be ordered by the most recently updated content within each sub term.
Note that if using a prison context, this again will be applied to the results.
So only content available in the current prison will be used for sorting.
