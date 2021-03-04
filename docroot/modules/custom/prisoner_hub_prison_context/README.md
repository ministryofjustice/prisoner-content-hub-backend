# Prisoner Hub Prison Context

This module loads in the Prison taxonomy term from a url parameter (i.e the current prison context).
It doesn't do anything more than that, it's up to other modules to implement functionality based on this.

# Dependencies
- taxonomy_machine_name:
  A contrib module that gives taxonomy terms "machine names".  These are nicer to work with than term IDs.
- jsonapi: The core Drupal jsonapi module, we are copying the jsonapi url routes so we need this module to be enabled.

## Module functionality
There are two main things this module does to provide this functionality.

# PrisonContext.php
This is a "paramconvertor" service, this tells Drupal that it can convert url parameters.
Any routes that have a "prison_context" type parameter will be converted by to a Prison taxonomy term by this class.
See https://www.drupal.org/docs/8/api/routing-system/parameters-in-routes/using-parameters-in-routes for more info.

# RouteSubcriber.php
This event listener adds in new routes that are copies of the jsonapi modules routes.  These include the prison_context
url parameter (converted by the PrisonContext.php service).  An example of a copied route:
Current: `/jsonapi/node/article`
Copied: `/jsonapi/prison/{prison}/article`

## Relevant Trello card
https://trello.com/c/JxFpJDkr/1944-determine-and-implement-method-for-passing-the-current-prison-to-drupal
