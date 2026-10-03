# Changelog

All notable changes to this extension are documented here. The format
is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [1.1.8] - 2026-10-03

### Fixed
- The Order column of the withdrawal requests grid escapes the order number and link before they are rendered as HTML.
- With "Period Starts From" set to "Shipment date (date of receipt)", the withdrawal window now starts at the earliest shipment date of the order, whatever order the shipments are loaded in. Previously the first shipment returned by the collection was used.
