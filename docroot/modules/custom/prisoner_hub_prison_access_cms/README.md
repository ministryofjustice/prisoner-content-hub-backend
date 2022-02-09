# Prisoner Hub Prison Access CMS

This module provides entity access rules relating to CMS access.  I.e. whether or not a user
can make edits to a piece of content.

It uses the value from the user Prison field (field_user_prisons) and matches this with
the value of the Prison owner field (field_prison_owner).  So that only users from certain prisons
can make edits to the content.

It also supports:
- users with multiple prisons (they will have edit access to all content within those prison)
- content owned by multiple prisons (a user with at least one of those prisons can make edits)
- users assigned to a prison category (they will have edit access to all content within that category)
- content assigned to a prison category (a user with at least one prison within that category can make edits)

## Dependencies
- prisoner_hub_prison_access:
  Whilst this module deals with access rules relating to viewing content (e.g. through JSON:API).
  There is some shared functionality/configuration with this module, so is marked as a dependency.

## How does this module use Drupal's permission system?
This module provides just one permission: "bypass prison ownership edit access".
When a user has this permission, no changes will be made by this module.

## Background as to why this module was created.
The standard set of Drupal permissions that come with Drupal, give either full edit access, or
none at all.
In our case, we still want users to be able to edit content (that is not part of their prison),
as they need to be able to add their own prison to the "Exclude from prison" field (but just
nothing else).
Therefore, the Drupal permissions need to allow the user to make modifications.
This module then sets all the fields on the edit form to be disabled (except for the
"Exclude from prison" field).

An alternative option was to use the field_permissions contributed module, this can control
access to each field, which is done via the Drupal permission system.
However, this would create a large amount of permissions (as each field would be a
separate permission), which would become difficult to manage.
