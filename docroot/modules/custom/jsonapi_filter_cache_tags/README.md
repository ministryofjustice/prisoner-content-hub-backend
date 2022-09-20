# JSON:API Filter Cache Tags

## Background
This module adds cache tags based on JSON:API filter values.

By default, JSON:API places _very generic_ `node_list` cache tags on all collection resources.
This approach leads to lots of unnecessary invalidations, as the `node_list` cache tag is invalidated on _any_ node
CRUD operation.
For example say you had two category taxonomy terms on your site, and you were retrieving content for them separately:
- /jsonapi/node/page?filter[field_category.id]=38f0565a-35d4-11ed-a261-0242ac120002
- /jsonapi/node/page?filter[field_category.id]=20bf54fa-b239-4b3a-bf4c-b7fd2db5ce3
Both of these requests would get the `node_list` cache tag.  If you created or updated content in one category, it
would invalidate both requests.

See https://www.drupal.org/node/3090131, which is an issue to provide per bundle specific cache tags.
However, this module goes further than that.  Instead, providing cache tags based on filter values.

This module removes the `node_list` cache tag, and provides a new one, which looks like:
`jsonapi_filter:node:field_category:20bf54fa-b239-4b3a-bf4c-b7fd2db5ce3`
This module also then invalidates this cache tag, _only_ when content has a field_category referencing that uuid.

This means that there are much fewer invalidations, which results in much better performance.

## Limitations
Currently, only entity reference by uuid filters are supported.  I.e. the filter needs to look exactly like:
`?filter[field_reference_field_name.id]=UUID`
No other field types are currently supported.
Also only the "=" operator is supported.  All other operators (e.g. "!=", "IN" or "NOT IN") are not supported.
Condition groups are not supported, the filter needs to be outside a group.
For all unsupported filters, it will revert to the default cache tag handling functionality, i.e. it will get the
`node_list` cache tag.
