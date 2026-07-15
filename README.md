# Search Elastic

<!-- OSPO-managed README | Generated: 2026-04-16 | v2 -->

[![License](https://img.shields.io/badge/License-GPL--2.0-blue.svg)](LICENSE) [![ownCloud OSPO](https://img.shields.io/badge/OSPO-ownCloud-blue)](https://kiteworks.com/opensource) [![Docker Hub](https://img.shields.io/docker/pulls/owncloud)](https://hub.docker.com/r/owncloud/server)

Search Elastic adds Elasticsearch-powered full-text search to ownCloud Server, enabling users to search the contents of documents stored in their ownCloud instance. It indexes all file types supported by Apache Tika -- including plain text, .docx, .xlsx, .pptx, .odt, .ods and .pdf files -- and returns results filtered by the user's access permissions.

## Part of Classic (OC10)

This app is part of the [ownCloud Server (OC10)](https://github.com/owncloud/core) ecosystem, extending its built-in search with full-text content indexing backed by Elasticsearch. It requires a running Elasticsearch instance connected to the ownCloud server.

The ownCloud Server is available on [Docker Hub](https://hub.docker.com/r/owncloud/server).

## Getting Started

Follow the steps below to install and configure Elasticsearch integration.

### Prerequisites

- A running [Elasticsearch](http://www.elasticsearch.org) instance

### Installation

Install from the [ownCloud Marketplace](https://marketplace.owncloud.com/), or manually:

```bash
cd apps
git clone https://github.com/owncloud/search_elastic.git
cd ..
php occ app:enable search_elastic
```

### Building from Source

```bash
make all                   # Install all dependencies
make dist                  # Build distribution package
```

### Running Tests

```bash
make test-php-unit         # Run PHP unit tests
make test-php-style        # Check code style with phpcs
make test-php-phpstan      # Run static analysis with PHPStan
```

## Documentation

- [Elasticsearch Search Configuration](https://doc.owncloud.com/server/latest/admin_manual/configuration/general_topics/search.html)
- [ownCloud Server Admin Manual](https://doc.owncloud.com/server/latest/admin_manual/)
- [FEATURES.md](FEATURES.md) for feature details
- [TESTING.md](TESTING.md) for testing instructions

## Community & Support

**[Star](https://github.com/owncloud/search_elastic)** this repo and **Watch** for release notifications!

- [ownCloud Website](https://owncloud.com)
- [Community Discussions](https://github.com/orgs/owncloud/discussions)
- [Matrix Chat](https://app.element.io/#/room/#owncloud:matrix.org)
- [Documentation](https://doc.owncloud.com)
- [Enterprise Support](https://owncloud.com/contact-us/)
- [OSPO Home](https://kiteworks.com/opensource)

## Contributing

We welcome contributions! Please read the [Contributing Guidelines](CONTRIBUTING.md)
and our [Code of Conduct](CODE_OF_CONDUCT.md) before getting started.

### Workflow

- **Rebase Early, Rebase Often!** We use a rebase workflow. Always rebase on the target branch before submitting a PR.
- **Dependabot**: Automated dependency updates are managed via Dependabot. Review and merge dependency PRs promptly.
- **Signed Commits**: All commits **must** be PGP/GPG signed. See [GitHub's signing guide](https://docs.github.com/en/authentication/managing-commit-signature-verification).
- **DCO Sign-off**: Every commit must carry a `Signed-off-by` line:
  ```
  git commit -s -S -m "your commit message"
  ```
- **GitHub Actions Policy**: Workflows may only use actions that are (a) owned by `owncloud`, (b) created by GitHub (`actions/*`), or (c) verified in the GitHub Marketplace.

## Security

**Do not open a public GitHub issue for security vulnerabilities.**

Report vulnerabilities at **<https://security.owncloud.com>** -- see [SECURITY.md](SECURITY.md).

Bug bounty: [YesWeHack ownCloud Program](https://yeswehack.com/programs/owncloud-bug-bounty-program)

## License

This project is licensed under the [GPL-2.0](LICENSE).

## About the ownCloud OSPO

The [Kiteworks Open Source Program Office](https://kiteworks.com/opensource), operating under
the [ownCloud](https://owncloud.com) brand, launched on May 5, 2026, to steward the open source
ecosystem around ownCloud's products. The OSPO ensures transparent governance, license compliance,
community health, and sustainable collaboration between the open source community and
[Kiteworks](https://www.kiteworks.com), which acquired ownCloud in 2023.

- **OSPO Home**: <https://kiteworks.com/opensource>
- **GitHub**: <https://github.com/owncloud>
- **ownCloud**: <https://owncloud.com>

For questions about the OSPO or licensing, contact ospo@kiteworks.com.

### License Migration to Apache 2.0

The OSPO is driving a strategic relicensing of ownCloud repositories toward the
[Apache License 2.0](https://www.apache.org/licenses/LICENSE-2.0), following
the [Apache Software Foundation's third-party license policy](https://www.apache.org/legal/resolved.html).

Individual repositories will migrate as their audit is completed. The LICENSE file
in each repo reflects its **current** license status (not the target).

**Current license: GPL-2.0** (Category X per Apache policy -- cannot be included in Apache-2.0 works).

Migration prerequisites for this repository:

- **CLA/DCO coverage**: All past contributors must have signed agreements permitting relicensing
- **Copyleft dependency audit**: All GPL dependencies must be replaced or isolated
- **KDE heritage review**: Any code with KDE-era copyrights requires legal analysis
- **Complete relicensing**: GPL-2.0 is a strong copyleft license; migration requires full relicensing of all files
