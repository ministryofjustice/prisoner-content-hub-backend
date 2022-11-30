vcl 4.0;
import std;
import directors;

# This Varnish VCL has been adapted from the Four Kitchens VCL for Varnish 3.
# This VCL is for using cache tags with drupal 8. Minor chages of VCL provided by Jeff Geerling.

# Default backend definition. Points to Apache, normally.
# Apache is in this config on port 80.
backend default {
    .host = "drupal-service.prisoner-content-hub-development.svc.cluster.local";
    .port = "8080";
    .first_byte_timeout = 300s;
}

# Access control list for PURGE requests.
# Here you need to put the IP address of your web server
acl purge {
    "127.0.0.1";
}

# Respond to incoming requests.
sub vcl_recv {
    # Add an X-Forwarded-For header with the client IP address.
    if (req.restarts == 0) {
        if (req.http.X-Forwarded-For) {
            set req.http.X-Forwarded-For = req.http.X-Forwarded-For + ", " + client.ip;
        }
        else {
            set req.http.X-Forwarded-For = client.ip;
        }
    }

    # Only allow PURGE requests from IP addresses in the 'purge' ACL.
    if (req.method == "PURGE") {
        if (!client.ip ~ purge) {
            return (synth(405, "Not allowed."));
        }
        return (hash);
    }

    # Only allow BAN requests from IP addresses in the 'purge' ACL.
    if (req.method == "BAN") {
        # Same ACL check as above:
        if (!client.ip ~ purge) {
            return (synth(403, "Not allowed."));
        }

        # Logic for banning based on tags
        # https://varnish-cache.org/docs/trunk/reference/vcl.html#vcl-7-ban
        if (req.http.X-Dropsolid-Purge-Tags) {
            # Add bans for tags but only for the current site requesting the ban
            ban("obj.http.X-Dropsolid-Purge-Tags ~ " + req.http.X-Dropsolid-Purge-Tags + " && obj.http.X-Dropsolid-Site == " + req.http.X-Dropsolid-Purge);
            return (synth(200, "Ban added."));
        }

        # Logic for banning everything
        if (req.http.X-Dropsolid-Purge-All) {
            # Add bans for the whole site
            ban("obj.http.X-Dropsolid-Site == " + req.http.X-Dropsolid-Purge);
            return (synth(200, "Ban added."));
        }

        # Throw a synthetic page so the request won't go to the backend.
        return (synth(403, "Missing headers for a ban"));
    }

    # Only cache GET and HEAD requests (pass through POST requests).
    if (req.method != "GET" && req.method != "HEAD") {
        return (pass);
    }

    # Pass through any administrative or AJAX-related paths.
    if (req.url ~ "^/status\.php$" ||
        req.url ~ "^/update\.php$" ||
        req.url ~ "^/admin$" ||
        req.url ~ "^/admin/.*$" ||
        req.url ~ "^/flag/.*$" ||
        req.url ~ "^.*/ajax/.*$" ||
        req.url ~ "^.*/ahah/.*$") {
           return (pass);
    }

    # Removing cookies for static content so Varnish caches these files.
    if (req.url ~ "(?i)\.(pdf|asc|dat|txt|doc|xls|ppt|tgz|csv|png|gif|jpeg|jpg|ico|swf|css|js)(\?.*)?$") {
        unset req.http.Cookie;
    }

    # Remove all cookies that Drupal doesn't need to know about. We explicitly
    # list the ones that Drupal does need, the SESS and NO_CACHE. If, after
    # running this code we find that either of these two cookies remains, we
    # will pass as the page cannot be cached.
    if (req.http.Cookie) {
        # 1. Append a semi-colon to the front of the cookie string.
        # 2. Remove all spaces that appear after semi-colons.
        # 3. Match the cookies we want to keep, adding the space we removed
        #    previously back. (\1) is first matching group in the regsuball.
        # 4. Remove all other cookies, identifying them by the fact that they have
        #    no space after the preceding semi-colon.
        # 5. Remove all spaces and semi-colons from the beginning and end of the
        #    cookie string.
        set req.http.Cookie = ";" + req.http.Cookie;
        set req.http.Cookie = regsuball(req.http.Cookie, "; +", ";");
        set req.http.Cookie = regsuball(req.http.Cookie, ";(SESS[a-z0-9]+|SSESS[a-z0-9]+|NO_CACHE)=", "; \1=");
        set req.http.Cookie = regsuball(req.http.Cookie, ";[^ ][^;]*", "");
        set req.http.Cookie = regsuball(req.http.Cookie, "^[; ]+|[; ]+$", "");

        if (req.http.Cookie == "") {
            # If there are no remaining cookies, remove the cookie header. If there
            # aren't any cookie headers, Varnish's default behavior will be to cache
            # the page.
            unset req.http.Cookie;
        }
        else {
            # If there is any cookies left (a session or NO_CACHE cookie), do not
            # cache the page. Pass it on to Apache directly.
            return (pass);
        }
    }
}

sub vcl_hash {
  /* Hash cookie data */
  # As requests with same URL and host can produce diferent results when issued with different cookies,
  # we need to store items hashed with the associated cookies. Note that cookies are already sanitized when we reach this point.
  if (req.http.Cookie) {
    /* Include cookie in cache hash */
    hash_data(req.http.Cookie);
  }

  /* Custom header hashing */
  # Empty in simple configs.
  # Example for caching differents object versions by device previously detected (when static content could also vary):
  # if (req.http.X-UA-Device) {
  #   hash_data(req.http.X-UA-Device);
  # }
  # Example for caching diferent object versions by X-Forwarded-Proto, trying to be smart about what kind of request
  # could generate diffetent responses.
  if ( req.http.X-Forwarded-Proto
    && req.url !~ "(?i)\.(bz2|css|eot|gif|gz|html?|ico|jpe?g|js|mp3|ogg|otf|pdf|png|rar|svg|swf|tbz|tgz|ttf|woff2?|zip)(\?(itok=)?[a-z0-9_=\.\-]+)?$"
    ) {
    hash_data(req.http.X-Forwarded-Proto);
  }

  /* Continue with built-in logic */
  # We want built-in logic to be processed after ours so we don't call return.
}

# Set a header to track a cache HITs and MISSes.
sub vcl_deliver {
    # Remove ban-lurker friendly custom headers when delivering to client.
    unset resp.http.X-Url;
    unset resp.http.X-Host;
    # Comment these for easier Drupal cache tag debugging in development.
    unset resp.http.Cache-Tags;
    unset resp.http.X-Dropsolid-Purge-Tags;
    unset resp.http.X-Dropsolid-Site;
    unset resp.http.X-Drupal-Cache-Contexts;

    if (obj.hits > 0) {
        set resp.http.X-Cache = "HIT";
    }
    else {
        set resp.http.X-Cache = "MISS";
    }
}

# Instruct Varnish what to do in the case of certain backend responses (beresp).
sub vcl_backend_response {
    # Set ban-lurker friendly custom headers.
    set beresp.http.X-Url = bereq.url;
    set beresp.http.X-Host = bereq.http.host;

    # Cache 404s, 301s, at 500s with a short lifetime to protect the backend.
    if (beresp.status == 404 || beresp.status == 301 || beresp.status == 500) {
        set beresp.ttl = 10m;
    }

    # Don't cache failed calls for image styles (see readme dropsolid_purge for more information)
    if (bereq.url ~ "(?i)\.(png|gif|jpeg|jpg|ico)(\?itok=.*)?$") {
        if (beresp.status == 503 || beresp.status == 500) {
            set beresp.http.cache-control = "no-cache";
            set beresp.ttl = 0s;
        }
    }

    # Don't allow static files to set cookies.
    # (?i) denotes case insensitive in PCRE (perl compatible regular expressions).
    # This list of extensions appears twice, once here and again in vcl_recv so
    # make sure you edit both and keep them equal.
    if (bereq.url ~ "(?i)\.(pdf|asc|dat|txt|doc|xls|ppt|tgz|csv|png|gif|jpeg|jpg|ico|swf|css|js)(\?.*)?$") {
        unset beresp.http.set-cookie;
    }

    # Allow items to remain in cache up to 6 hours past their cache expiration.
    set beresp.grace = 6h;
}
