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
