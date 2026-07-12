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

## Development

```sh
nix develop -c bin/check
```

The plugin has no runtime dependency on vpsAdmin, a network service, or a
particular vpsFree.cz checkout layout. The check suite also loads the plugin in
the DokuWiki version supplied by nixpkgs and renders an annotation through the
real parser.
