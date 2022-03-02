# Prisoner hub breadcrumbs

This module provides Drupal breadcrumb integration for the Prisoner content hub.

By default, Drupal's breadcrumbs are path based (see Drupal\system\PathBasedBreadcrumbBuilder)
and hierarchy based for taxonomy pages (see Drupal\taxonomy\TermBreadcrumbBuilder).

The TermBreadcrumbBuilder works for our breadcrumbs on categories.  But for series and content a
custom implementation is required.  This is provided in this module by two new BreadcrumbBuilder
services.
(See https://kporras07.medium.com/creating-breadcrumbs-in-drupal-8-3a5e6d888e5b for more info
on creating BreadcrumbBuilder services).

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
Builds breadcrumbs for series taxonomy terms based on their assigned categories.
e.g.
- Tier 1 category
  - Sub-category
    - Sub-sub-category
      - Series

### Dependencies
- node module (drupal core)
- taxonomy module (drupal core)

### Test dependencies
- computed_breadcrumbs (contrib)

This module provides the Drupal breadcrumb trail as a computed field, which means it gets
outputted in JSON:API.
The tests from this module use JSON:API for retrieving breadcrumbs, so the computed_breadcrumbs
module must be enabled for the tests to pass.
