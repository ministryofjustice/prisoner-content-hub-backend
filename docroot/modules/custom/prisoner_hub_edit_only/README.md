# Prisoner Hub Edit Only

This module provides functionality for making the Drupal CMS "edit only".

As all of our content is viewed in a separate frontend app (and retrieved via JSON:API).
It therefore doesn't make sense to allow content to be viewed directly in Drupal.
Not only does this provide confusing UX to content editors. It also exposes our content to
anonymous users.

This module overrides the "view" route and redirects them to the edit route.
This currently works for the following entity types:
- nodes (i.e. content)
- taxonomy terms

E.g.
User visits /node/123
User is redirected to /node/123/edit
If the user is logged in with access to editing the content, they will see the edit page.
If user is logged out or does not have editing access, they will get a 403.

This module also removes the "View" tab and replaces it with "Edit".

In the future, we may want to redirect the view page to the frontend app.  This would not be difficult
to do (as the url alias used in Drupal will match the one on the frontend).  However, we currently
would not know which prison to send the user to.  Once we store the prison for each user, we can redirect
the to the right place.
