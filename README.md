[![Packagist Version](https://img.shields.io/packagist/v/hn/video.svg)](https://packagist.org/packages/hn/video)
[![Packagist](https://img.shields.io/packagist/l/hn/video.svg)](https://packagist.org/packages/hn/video)
[![Packagist](https://img.shields.io/packagist/dt/hn/video.svg)](https://packagist.org/packages/hn/video)
[![Packagist](https://img.shields.io/packagist/dm/hn/video.svg)](https://packagist.org/packages/hn/video)
[![Build status](https://img.shields.io/bitbucket/pipelines/hauptsachenet/video/master.svg)](https://bitbucket.org/hauptsachenet/video/addon/pipelines/home)

# Video compression for TYPO3

This extension adds video conversion/compression capability to TYPO3.

## Why?

There are valid reasons to host videos yourself but correct video compression isn't too easy.
TYPO3 already handles image compression (at least sometimes). So wouldn't it be awesome if videos are managed too?  

## How does it work

- It starts with a new `FileRenderer` which can simply be used through the `<f:media>` view helper.
- This renderer will go through the normal TYPO3 file processing pipeline using a new `Video.CropScale` task.
- Videos are then processed either by the `ffmpeg` command or by [CloudConvert](https://cloudconvert.com).
- During processing, the `FileRenderer` will render a simple progress percentage.
- After processing is done the video will be rendered similar to the normal html5 video renderer.

## How to install

- install the extension
- either make sure that ffmpeg is available
  or configure a [CloudConvert](https://cloudconvert.com) api key in the extension settings
- make sure that the `video:process` command is run regularly.
  This command will run the conversion if you use local `ffmpeg`.
  If you use CloudConvert, this command is technically not required since everything can be handled though callbacks
  but it will increase the accuracy of the progress information and act as a fallback if the callbacks
  don't come though for whatever reason. 
  
## Configuration

By default, you'll get a level 3.0 h264 video stream (a 480p video stream) with an aac-lc audio stream.
This configuration ensures maximum compatibility but you might want higher quality or even lower quality.

The configuration is based on configurable presets for each stream.
You can configure formats in `$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['video']['formats']['{format-name}']`
and the default format is defined in `$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['video']['default_video_formats']`.

### change format defaults

Let's say 480p isn't enough for you and you want to upgrade to 720p (level 3.1).
You can do this in multiple places.

#### create or modify a format globally

```php
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['video']['formats']['mp4:default'] = [
    'fileExtension' => 'mp4',
    'mimeType' => 'video/mp4',
    'video' => [Preset\H264Preset::class, ['level' => 31]], // here is the important part
    'audio' => [Preset\AacPreset::class, []],
    'additionalParameters' => ['-movflags', '+faststart', '-map_metadata', '-1', '-f', 'mp4'],
];
```

#### adhoc while rendering

```html
<f:media file="{file}" additionalConfig="{formats: {mp4: {level: 31}}}" />
```

## Run the tests

This project has some basic tests to ensure that it works in all typo3 versions described in the composer.json.
These tests are run by bitbucket and defined in `bitbucket-pipelines.yml`.

To run them locally, there are some composer scripts provided in this project.
Just clone the project, run `composer install` and then `composer db:start`, wait a few seconds, then `composer test`.
You can also run `composer test -- --filter TestCase` to run specific text classes/methods/datasets.

Here is a list of available commands:

- `composer db:start` will start a database using a docker command.
  You don't have to use it if you have a database available but you'll need to define the `typo3Database*` variables.
- `composer db:stop` unsurprisingly stops the database again... and removes it.
- `composer test` will run all available tests. If your first run fails then you might want to run `cc`.
- `composer test:unit` will just run the unit tests.
- `composer test:functional` will just run the functional tests.
- `composer cc` will remove some temp files. If your functional test fail for no apparat reason try this.
