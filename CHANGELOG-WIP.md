# Release notes for Craft CMS 4.14 (WIP)

## Content Management
- The global sidebar no longer shows “Failed” for queue jobs, for users that don’t have access to the Queue Manager. ([#16184](https://github.com/craftcms/cms/issues/16184))

## Administration
- Added the `elements/delete-all-of-type` command. ([#16423](https://github.com/craftcms/cms/pull/16423))
- Added the `utils/delete-empty-volume-folders` command. ([#16388](https://github.com/craftcms/cms/issues/16388))
- The Queue Manager utility now shows jobs’ class names. ([#16228](https://github.com/craftcms/cms/pull/16228)) 

## Development
- Added the `primarySite` global Twig variable. ([#16370](https://github.com/craftcms/cms/discussions/16370))
- The `duration` Twig filter now has a `language` argument. ([#16332](https://github.com/craftcms/cms/pull/16332))
- Added support for specifying the current site via an `X-Craft-Site` header set to a site ID or handle. ([#16367](https://github.com/craftcms/cms/pull/16367))
- Deprecated the `ucfirst` Twig filter. `capitalize` should be used instead.

## Extensibility
- Added `craft\helpers\Image::EXIF_IFD0_ROTATE_0_MIRRORED`.
- Added `craft\helpers\Image::EXIF_IFD0_ROTATE_0`.
- Added `craft\helpers\Image::EXIF_IFD0_ROTATE_180_MIRRORED`.
- Added `craft\helpers\Image::EXIF_IFD0_ROTATE_270_MIRRORED`.
- Added `craft\helpers\Image::EXIF_IFD0_ROTATE_90_MIRRORED`.
- Added `craft\models\AssetIndexingSession::$forceStop`. ([#16435](https://github.com/craftcms/cms/pull/16435))
- `GuzzleHttp\Client` is now instantiated via `Craft::createObject()`. ([#16366](https://github.com/craftcms/cms/pull/16366))
- `craft\helpers\DateTimeHelper::humanDuration()` now has a `$language` argument. ([#16332](https://github.com/craftcms/cms/pull/16332))

## System
- Database rows with foreign keys referencing nonexistent rows are now deleted via garbage collection.
- Pages which contain image transform generation URLs now set no-cache headers. ([#16195](https://github.com/craftcms/cms/discussions/16195))
- Action requests (such as `actions/app/health-check`) now send no-cache headers by default. ([#16364](https://github.com/craftcms/cms/pull/16364))
- Image cleansing now preserves the original image quality, if known.
- Fixed a bug where `craft\config\GeneralConfig::safeMode()` set Safe Mode to `false` by default.
- Fixed a bug where Craft wasn’t auto-rotating or flipping images uploaded with a mirrored EXIF orientation.
- Fixed a bug where asset indexing could get stuck in an infinite loop if the index data was deleted. ([#16435](https://github.com/craftcms/cms/pull/16435))
- Updated Twig to 3.15. ([#16207](https://github.com/craftcms/cms/discussions/16207))
