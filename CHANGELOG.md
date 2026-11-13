# Changelog

All notable changes to this project are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Changed

- Facts no longer raise the conformance level themselves; the signing
  extension sets it from the finished record.

### Fixed

- `validFrom` and `validUntil` were one day early on servers east of UTC: date
  fields hold midnight in the server time zone and were formatted in UTC.
- Security: the facts endpoint published facts of pages below a hidden or
  access-restricted page (`extendToSubpages`) to anonymous visitors, including
  the quoted words. It now checks every page above the fact's page through
  `PublicPageRepository`.
- Claim identifiers and Web Annotation URLs were relative when the site base
  was relative, such as `/`. They are now absolute, completed from the
  request.
- Labels of the facts field and tab resolve instead of showing raw keys.

### Added

- "Fact" button for CKEditor 5 (RTE preset `provenance`): creates a fact for
  the selected words through a backend AJAX route with the editor's
  permissions.
- Documentation pointing to the manual of the core extension (milestone M7).
- Facts on content elements (milestone M6): data model, page anchors with
  Claim Microdata, `claim` list in the provenance record, Web Annotations
  under `/.well-known/content-provenance/facts/{factId}`, conformance level 4
  together with signing and audit.
- Repository skeleton: composer.json, ext_emconf.php, Services.yaml,
  extension icon, contribution guide (milestone M1).
