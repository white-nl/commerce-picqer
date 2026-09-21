# Release Notes for Craft Picqer Plugin

## 3.0.0 - 2026-06-04

### Added

 - Added Craft CMS 5 and Craft Commerce 5 compatibility.
 - Added a new plugin setting to choose which Craft Commerce inventory location receives stock updates from Picqer.

### Changed
 - Updated stock synchronization to use Craft Commerce 5 inventory management instead of directly writing variant stock values.

## 2.2.0 - 2025-09-10

### Added

 - Added a new `PicqerApi::EVENT_GET_ORDER_LINE_ITEMS_TO_PUSH` event that allows to filter order line items sent to Picqer.

## 2.1.0 - 2025-04-28

### Changed

 - Check if the warehouse is `active` and the `counts_for_general_stock` is enabled before setting the stock

## 2.0.0 - 2022-06-01

### Added
- Added Craft CMS 4 and Craft Commerce 4 compatibility

## 1.1.0 - 2021-09-23

### Added
- Manual order upload feature.

## 1.0.0 - 2021-07-28

- Initial release.
