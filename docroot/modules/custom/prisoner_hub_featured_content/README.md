# Prisoner Hub Featured Content

This module provides custom CMS enhancements relating to the featuring content on categories.

The "Feature on category" field, which is an entity reference field, is modified to only show categories that the
category taxonomy terms that have already been selected in either the
- `field_moj_top_level_categories`
- `field_category`
- `field_moj_series`

Note that functionality to synchronise the references between featured content and the categories, is provided by the
"Corresponding Entity Reference" (cer) module.  This means that when a category has been selected in `field_feature_on_category`
The category is then updated, to also contain a reference back to the content.  This allows the list of featured content to
be controlled from both the category and the featured content.
