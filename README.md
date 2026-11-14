# Content Provenance: Facts

Addressable facts within content, exposed as Microdata and W3C Web Annotations.

TYPO3 reference implementation of **Layer 3** of the
[Content Provenance Specification](https://github.com/dkd-dobberkau/content-provenance-spec). Conformance level reached: **L4 Citable (with audit)**.

Optional extension. Requires the core extension [`provenance`](https://github.com/dkd-dobberkau/typo3-provenance).

> **Status: experimental, pre-release.** Milestone M6 of the
> [PRD](https://github.com/dkd-dobberkau/cms-provenance.org/blob/main/docs/prd-typo3-extensions.md)
> is in place, including the rich text editor button. Together with
> the signing and audit extensions a site reaches conformance level L4.
> Nothing here is ready for production use.

## How it works

Editors add facts to a content element in the tab **Provenance facts**. A
fact names the **quoted text** in the element, a **subject** (URI), a
**predicate** (URI or schema.org term), a **value** (literal or URI),
optional **evidence**, a **confidence** and a validity period.

On the page, with the site set **Content Provenance: facts**
(`cms-provenance/provenance-facts`):

- the quoted text is wrapped in `<span id="fact-{factId}">` with schema.org
  `Claim` Microdata, so `https://example.org/page#fact-revenue-2025` points at
  it; the visible text does not change, so signatures stay valid;
- the page's provenance record gets a `claim` list, which the signing
  extension signs along with the rest;
- `/.well-known/content-provenance/facts/{factId}` returns the fact as a W3C
  Web Annotation, targeting the fragment and the quoted text
  (`TextQuoteSelector`).

With the signing and audit extensions installed, records with facts claim
`cpsConformance: 4`.

Limits of this version:

- The quote must sit inside one paragraph without formatting in between;
  otherwise it is not anchored on the page (the annotation still identifies
  it by its text).
- The site set uses `tt_content.stdWrap.postUserFunc`; a site package that
  sets it as well overrides one of the two.
- Facts can be created from the rich text editor: select words, press
  **Fact**, describe the fact. Enable the RTE preset `provenance`
  (`RTE.default.preset = provenance`) or add the plugin to your own preset,
  see the manual. The text is not changed; the quote is matched on the page.

### Facts overview

The backend module **Content > Provenance > Facts** (Web > Provenance in
TYPO3 13) lists all facts of the selected page and its subpages, with search,
filters and a link to edit each fact's content element.

### Lookups in the fact dialog

Other extensions can help editors research a fact in the **Fact** dialog. They
add ES module specifiers to the editor configuration key
`provenanceFact.lookups`, for example in a listener for rte_ckeditor's
`AfterPrepareConfigurationForEditorEvent`. When the dialog opens, each module
is imported and its default export is called with a context object:

- `form`: the dialog's form element;
- `quote`: the selected words;
- `panel`: an empty element above the fields for the lookup's own interface;
- `fill(values)`: sets any of `subject`, `predicate`, `objectLiteral`,
  `objectUri` and `sourceUrl` and marks the changed fields.

A lookup that fails to load is skipped with a console warning; the dialog
works without it.

## Requirements

- TYPO3 13.4 LTS or 14
- PHP 8.2 or later

## Installation

Not yet released. Once a first version is tagged:

```bash
composer require cms-provenance/provenance-facts
```

Extension key: [`provenance_facts`](https://extensions.typo3.org/extension/provenance_facts)

## Links

- Project site: <https://cms-provenance.org>
- Specification: <https://github.com/dkd-dobberkau/content-provenance-spec>
- Product requirements (PRD): <https://github.com/dkd-dobberkau/cms-provenance.org/blob/main/docs/prd-typo3-extensions.md>
- Conformance test suite: <https://github.com/dkd-dobberkau/content-provenance-conformance>

## Documentation

Installation, configuration, the editorial guide and verification are
documented in the [manual of the core extension](https://github.com/dkd-dobberkau/typo3-provenance/blob/main/Documentation/Index.rst).

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
