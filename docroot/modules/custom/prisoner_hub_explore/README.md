# Prisoner hub explore

Provides a JSON:API resource for "exploring" content.
Currently, this means the content is ordered by random.
```
/jsonapi/node/explore
```

## Why not use a url parameter for random sorting?
When working on this module, we looked into adding the option to specify random sorting as
part of the sort parameter on a url.  I.e. `?sort=random`.  This is not something supported
by Drupal's JSON:API, as it only allows you to sort on fields.

It was possible to add in this functionality, by overriding the controller from the core
jsonapi module.  However, it was incompatible with other modules overriding the same
controller, and generally felt like the incorrect approach.
See https://www.drupal.org/project/drupal/issues/2917793#comment-14535187
