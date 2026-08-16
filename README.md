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
  source="contract/pages/manuals-vps-kvm.txt"
  test="kb/kvm#*"
/>
```

The `source` value is a path within the configured repository. The `test` value
is a test-runner selector ending in `#*`; the example selects every script in
the `kb/kvm` suite. The plugin links that selector to
`tests/suite/kb/kvm.nix` and displays the runnable selector in the editor
warning.

Configure these DokuWiki plugin settings:

- `managed_repository_url`: an HTTPS GitHub repository URL, such as
  `https://github.com/vpsfreecz/vpsfree-kb-contracts`
- `managed_repository_ref`: `master` or a lowercase 40-character commit hash
- `managed_repository_ref_file`: an optional absolute path to a file containing
  the revision

When a revision file is configured, it takes precedence over the static
revision. The plugin reads it on every request, so staging can switch commits
without a plugin deployment or container restart. The staging configuration
uses `/private/kb-staging/managed-repository.ref`.

The marker emits no article XHTML. The plugin uses the resolved GitHub links
for the **Source on GitHub** page tool and for a localized warning in edit,
preview, source, and locked views. The warning links to the source, test suite,
and editing guide. All three links open in a new tab to preserve the open edit
form. The plugin does not fetch repository content.

Legacy markers containing two full GitHub blob URLs remain supported during
the rollout. Invalid markers render a visible diagnostic. Invalid repository
configuration produces a visible warning in editor views and does not create a
page tool. The standalone article contract checker verifies that each managed
page has one marker with its registered source and test selector.

## Development

```sh
nix develop -c bin/check
```

The plugin has no runtime dependency on vpsAdmin, a network service, or a
particular vpsFree.cz checkout layout. The check suite also loads the plugin in
the DokuWiki version supplied by nixpkgs and renders an annotation through the
real parser.
