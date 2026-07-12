# vpsAdmin Documentation DokuWiki Plugin

This repository contains the `vpsadmindoc` DokuWiki plugin. Keep it independent
of the vpsFree.cz workspace and deployment configuration at runtime.

The plugin annotates human-written documentation; it must never fetch labels or
content from a live vpsAdmin instance. Visible annotation bodies remain the
source of localized prose. Semantic IDs are lowercase, stable, and describe
user intent rather than display order.

Treat all wiki source as untrusted. Escape every value written to HTML, accept
only explicitly validated attributes, and render malformed annotations with a
visible diagnostic. Do not add write-capable actions or remote methods without
an explicit security review.

Run `nix develop -c bin/check` before committing. Keep the plugin compatible
with the DokuWiki extension APIs used by the currently deployed DokuWiki
release. Do not add GitHub workflows; checks are operator-run.
