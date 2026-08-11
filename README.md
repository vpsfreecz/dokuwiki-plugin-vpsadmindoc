# vpsAdmin documentation annotations for DokuWiki

`vpsadmindoc` annotates vpsAdmin navigation instructions with stable semantic
IDs while leaving the visible, localized documentation text under normal wiki
editor control.

```text
<vpsadmin-nav id="member.public-keys.add">Edit profile → Public keys → Add public key</vpsadmin-nav>
```

The XHTML renderer emits an inline element with
`data-vpsadmin-doc-id="member.public-keys.add"`. The metadata renderer records
the same ID under `vpsadmindoc.navigation`. External tooling can compare those
IDs with the vpsAdmin WebUI and screenshot contract without scraping translated
sentences.

Malformed IDs are rendered with a visible warning. The plugin validates syntax
only; whether an ID exists, is retired, or has matching Czech and English
annotations is checked by the standalone contract checker.

Repository-managed pages carry an invisible marker immediately after their
language mapping:

```text
<kb-managed
  source="https://github.com/vpsfreecz/vpsfree-kb-contracts/blob/master/contract/pages/manuals-vps-kvm.txt"
  test="https://github.com/vpsfreecz/vpsfree-kb-contracts/blob/master/tests/suite/kb/kvm.nix"
/>
```

The marker emits no XHTML. Its validated GitHub links are stored in page
metadata and used to add a **Source on GitHub** page tool. DokuWiki editor and
preview views also show a localized warning with links to the canonical source
and automated test. The plugin never fetches repository content at runtime.

Only HTTPS `github.com` blob links are accepted. Invalid markers render a
visible diagnostic and do not create toolbar or editor links. The standalone
article contract checker verifies that a page has exactly one marker and that
its links match the registered source and test.

## Development

```sh
nix develop -c bin/check
```

The plugin has no runtime dependency on vpsAdmin, a network service, or a
particular vpsFree.cz checkout layout. The check suite also loads the plugin in
the DokuWiki version supplied by nixpkgs and renders an annotation through the
real parser.
