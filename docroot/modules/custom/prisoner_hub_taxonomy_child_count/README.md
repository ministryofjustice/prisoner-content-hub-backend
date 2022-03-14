# Computed Taxonomy Child Count

Displays a count of child taxonomy terms as a computed field.
This will display by default in JSON:API.
Example JSON:API output of the field:
```
"attributes": {
  ...
  "child_term_count": {
    "sub_categories_count": "3",
    "sub_series_count": "23"
  }
  ..
```
`sub_categories_count` show the number of child taxonomy terms within the same vocabulary.
`sub_series_count` show the number of series referencing the category via `field_category`.

## Why?
The relationship between child -> parent is one way, i.e. the child references the parent.
This means that parent terms don't actually have any reference to their child terms, so it
is not possible to bring them in using JSON:API `?include` to determine whether or not they exist.

Whilst it is possible to query using the parent id as a filter, e.g. `?filter[parent]=uuid`,
this would involve separate JSON:API requests per taxonomy term to work out which has child
terms and which doesn't.  The computed field from this module does all this for you.

## Note
You cannot filter on the `child_term_count` field, as it is computed (i.e. it is calculated
at run-time).
