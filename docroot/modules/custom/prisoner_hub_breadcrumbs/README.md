# Prisoner hub breadcrumbs

This module provides custom breadcrumb integration for the Prisoner content hub.

It does this using two BreadcrumbBuilder services:
### NodeBreadcrumbBuilder
Builds breadcrumbs for nodes that are assigned to a series or category.
e.g.
- Tier 1 category
  - Sub-category
    - Sub-sub-category
      - Series
        - Content (node)

### TermBreadcrumbBuilder
Builds breadcrumbs for taxonomy terms based on their assigned categories.
e.g.
- Tier 1 category
  - Sub-category
    - Sub-sub-category
      - Series

Note this currently only applies to series, as the core TermBreadcrumbBuilder covers the requirements
of categories themselves (which just use their own hierachy for breadcrumbs).  The tests in this module cover
both series and categories.

### Dependencies
- node module (drupal core)
- taxonomy module (drupal core)

### Test dependencies
- computed_breadcrumbs (contrib)

This module provides the Drupal breadcrumb trail as a computed field, which means it gets
outputted in JSON:API.
The tests from this module use JSON:API for retrieving breadcrumbs, so the computed_breadcrumbs
module must be enabled for the tests to pass.
