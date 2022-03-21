<!---
Full module name and description.
-->
# farm_myfarmkey
Integrates farmOS with [cibolabs MyFarmKey](https://www.cibolabs.com.au/myfarmkey).

This module is an add-on for the [farmOS](http://drupal.org/project/farm)
distribution.

<!---
Geting started.
-->
## Getting started

<!---
Document installation steps.
-->
### Installation

Install as you would normally install a contributed drupal module.

<!---
Document features the module provides.
-->
## Features

### Importers

This module provides an importer for the GeoJSON file included in the standard
MyFarmKey report. Each property is imported into farmOS as a Property (land)
asset using the `jurisdicational_id` as the name. The `plan_number` and
`lot_number` are saved as ID tags on the asset. Additional metadata is saved
in the asset notes and data field.


<!---
Document features related to a single bundle.
-->
### Land assets

Adds two id tag types for land assets:
- Plan number: The plan number specified by MyFarmKey.
- Lot number: The lot number specified by MyFarmKey.

<!---
It might be nice to include a FAQ.
-->
## FAQ

<!---
Include maintainers.
-->
## Maintainers

Current maintainers:
- Paul Weidner [@paul121](https://github.com/paul121)

<!---
Include sponsors.
-->
## Sponsors
This project has been sponsored by:
- [Regen Digital](https://regenfarmersmutual.com/regendigital/)
