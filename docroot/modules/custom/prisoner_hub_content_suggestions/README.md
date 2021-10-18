# Prisoner Hub Content Suggestions

This module provides a custom JSON:API resource for content suggestions.

`/jsonapi/node/{resource_type}/{entity}/suggestions`
e.g.
`/jsaonpi/node/page/4c20706e-6b36-4272-831d-6000410fa34c/suggestions`

The suggestions is based on a set of rules.
Currently:
- Any content with the same secondary tag
- Any content with the same category
- Any content that is in a series that shares the same category as the series of the current content
- Content that is in the same series will be excluded
- Content will be sorted by random


