# Prisoner hub primary nav

This module provides support for retrieving the "primary navigation" menu via JSON:API.
A new route is provided:
`/jsonapi/primary_navigation`

This route is automatically updated to use prison context (via the prisoner_hub_prison_context)
so you can access a prisons primary nav via:
`/jsonapi/prison/{prison}/primary_navigation`

The Drupal menu that is returned as the primary nav is determined from the prison.
If a menu has been specified with `field_primary_naviation`, then this will be used.
Otherwise the `default-primary-navigation` will be used.

## Dependencies:
### JSON:API Menu Items
https://www.drupal.org/project/jsonapi_menu_items
The jsonapi_menu_items module provides a JSON:API resource for menu items, via a route
`/jsonapi/menu_items/{menu}`.
This module decorates the `MenuItemsResource` to dynamically assign the menu
based on the current prison.

### Prisoner hub prison context
This module is required to modify jsonapi urls to accept the prison context.  This way
the specific menu for each prison can be determined.

## Dependent configuration
This module assumes the following configuration exists on the site.
- `field_primary_navigation`
  - This field allows prisons to optionally pick a specific menu.
- `system.menu.default-primary-navigation`
  - This is the default menu that is used when one isn't found for the current prison.
