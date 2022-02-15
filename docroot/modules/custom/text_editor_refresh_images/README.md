# Text Editor Refresh Images

Refresh image urls when editing text inside a text editor.

This module is useful for when image urls are regularly updated.
e.g. when using amazon s3 presigned urls.

Drupal has a concept of text filters, which process text to make certain modifications.
One of those filters is "Track images uploaded via a Text Editor", which updates image urls
to their latest versions (e.g. with an s3 signature).
However, text filters are only applied to the output text.  The input text is never processed,
the idea being that the "original" state is always stored.
@See https://www.drupal.org/node/213156
This means image urls will always stay the same as when they were initially uploaded.

This module applies the "Track images uploaded via a Text Editor" filter to the input text.
Refreshing the image urls when editing content.

Note that the filter is applied regardless of text format.

### Alternatives
An alternative solution would be to use https://www.drupal.org/project/entity_embed
However, this is slightly more complex, and better suited to when media entities are being used.
