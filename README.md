# Content Provenance: Facts

Addressable facts within content, exposed as Microdata and W3C Web Annotations.

TYPO3 reference implementation of **Layer 3** of the
[Content Provenance Specification](https://github.com/dkd-dobberkau/content-provenance-spec). Conformance level reached: **L4 Citable (with audit)**.

Optional extension. Requires the core extension [`provenance`](https://github.com/dkd-dobberkau/typo3-provenance).

> **Status: experimental, pre-release.** This repository currently contains the
> skeleton only (milestone M1 of the [PRD](https://github.com/dkd-dobberkau/cms-provenance.org/blob/main/docs/prd-typo3-extensions.md)). Nothing here is
> ready for production use.

## Scope

- Fact data model (subject, predicate, object, source, confidence, validity)
- Rich text editor button for marking up facts
- Microdata output inline in the rendered HTML
- CLI: `provenance:facts:list`

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

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
