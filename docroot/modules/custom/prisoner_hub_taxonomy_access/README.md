# Prisoner hub taxonomy access

This module deals with access control rules for taxonomy terms, based on the content inside them.

Currently, it works on two type of taxonomy vocabularies.

### Series
If a series has no available content, then the user is denied access.

### Categories
If a category has no available content assigned directly to that category, to a series that is
assigned to the category, or to a sub-category (or any child category), then the user is denied access.

By available content, this refers to anything that is picked up using a standard \Drupal::entityQuery().
This means that other modules that implement QueryAccess rules (e.g. prisoner_hub_prison_access) will
also be picked up.  I.e. prison category rules will be applied to the content.

## Future improvements
This module uses hook_entity_access(), which is run whenever a taxonomy term is loaded.
We should look at moving to a QueryAccessSubscriber class, as this has several benefits:
- It's run as part of the entity query, which means that it is included in the count of returned items
from JSON:API.  e.g. if there are 40 total available items with 5 not available, when requesting 40 you
will always get 40 on that first page.  Whereas with hook_entity_access() you could get 35 on page 1, and
then the other 5 on page 2.
- It's better for performance, as with hook_entity_access() is run individually per entity, meaning in our
case we are run an additional entity query per taxonomy term.

Unfortunately, because entity queries do not support reverse relationships, it's not straightforward to
implement a QueryAccessSubscriber for the rules above (as the relationships go from node -> taxonomy and
not the other way round).
https://trello.com/c/kd0jBdDA is a card that will "sync" prison field data between content and taxonomy, so
this would potentially solve the issue.


