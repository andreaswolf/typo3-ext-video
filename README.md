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

- It starts with a new `FileRenderer` which automatically kicks in if you use the `<f:media>` view helper.
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
  
## Simple Configuration

There are some basic configuration options within the ext_conf which you can set though the TYPO3 backend globally.

- how to use ffmpeg (CloudConvert or ffmpeg command)
- choose between performance presets like h264 slow, veryslow and if you want to also encode vp9
- change the codec level to change resolution, filesize and compatibility
- decide on video/audio compression using an easy percentage value that's similar to the jpeg quality percentage

These options are read using TYPO3 9's `ExtensionConfiguration` class so if you use TYPO3 9,
you can also define these options programmatically in you `AdditionalConfiguration.php` like in this example:

```php
<?php
if (getenv('CLOUDCONVERT_APIKEY')) {
    $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['video']['converter'] = 'CloudConvert';
    $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['video']['cloudConvertApiKey'] = getenv('CLOUDCONVERT_APIKEY');
}
```
  
## In-Depth Configuration

To understand the the configuration, you'll need to know some basics first.
There are 3 levels:

1. the *format*: eg. mp4 or webm.
   The format defines what the file is supposed to be, which streams are in it, what mime type it is
   and format specific ffmpeg parameters. The streams are the interesting part.
2. the stream *preset*: which defines an audio or video stream. Examples: `H264Preset` and `AacPreset`.
   Presets are classes which define how the ffmpeg command will look like.
   They are fairly complex but can create your own ones if you need a specific format that i haven't created.
   But most likely you want to configure the existing presets.
3. *preset configuration*: Is a simple array which maps onto the setters of the Preset.
   There you can tune compatibility, resolution, framerate and quality.
   This is what you are most likely interested in.


### The format definition

```php
<?php
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['video']['formats']['mp4'] = [
    'fileExtension' => 'mp4',
    'mimeType' => 'video/mp4',
    'video' => [\Hn\Video\Preset\H264Preset::class],
    'audio' => [\Hn\Video\Preset\AacPreset::class],
    'additionalParameters' => ['-movflags', '+faststart', '-map_metadata', '-1', '-f', 'mp4'],
];
```

That is the default format definition of the mp4 video container. A format definition consists of these parts:

- `fileExtension` which simply defines what file extension the resulting file must have.
  While the default format definitions use the file extension also as the identifier, yours don't have to.
- `mimeType` for the `<source type="">`. Although a codec extension will be added.
- `video` defines a *preset* for the video stream. Omit or set to null if your format does not require/support video.
  You can add a second argument with options which will be passed to the constructor
  (if not overridden in other places).
- `audio` defines a *preset* for the audio stream. Omit or set to null if your format does not require/support audio.
- `subtitle` defines a *preset* for the subtitle stream. There are none implemented by default but the option is there.
- `data` defines a *preset* for the data stream. There are none implemented by default but the option is there.
- `additionalParameters` is an array of parameters that are added to the ffmpeg command

You can configure formats in `$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['video']['formats']['{format-name}']`.
There is a list of formats that is used by default.
It is defined in `$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['video']['default_video_formats']` and looks like this:

```php
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['video']['default_video_formats'] = [
    // 'webm' => [], // this format is by default disabled but can be enabled in ext_conf.
    'mp4' => [],
    
    // you can even pass options to the presets within here
    'mp4' => ['level' => '4.0']
    // in this case i increate the compatibility level to 4.0 which allows full-hd.
    // read more about that in the preset configuration.
];
```

The other way of using the format is ad-hoc within the media view helper.

```html
<f:media file="{file}" additionalConfig="{formats: {mp4: {}}}" />
<!-- you can pass preset options here as well -->
<f:media file="{file}" additionalConfig="{formats: {mp4: {video: {quality: 0.6, width: 400, height: 400, crop: 1}}}}" />
```

### The preset

The presets are classes which define how a stream is handled.
You probably want to understand the basic concept of them
because it'll make it easier to understand the preset configuration.

- `PresetInterface` is the base and explains what you need.
  A minimal preset would just define `getParameters`
  which must return an array of ffmpeg arguments like `['-c:v', 'libx264']`.
  The preset configuration is simply passed as an array to the constructor.
  A result of `ffprobe` is passed as an argument to `getParameters`
  so that decisions can be implemented based on the source material.
- `AbstractPreset` is a base implementation that handles options by searching a setter method for them.
  So that the option `quality` is passed as `setQuality`.
- `AbstractCompressiblePreset` sits on top of the `AbstractPreset` and adds a 2 concepts
    - an abstraction over the quality using a value `> 0.0` and `<= 1.0` which should roughly equal jpeg's options
    - the "this stream does not need to be touched" so that a stream with equal or lower quality doesn't get re-encoded
- `AbstractVideoPreset` and `AbstractAudioPreset` start to go into specifics of the stream type.
  The video preset handles framerate, video resolution and cropping.
  The audio preset handles channels and sample rates.
  If you want/need to implement eg. H265 support, you probably want to extend one of those.
- `AacPreset`, `H264Preset`, `OpusPreset`, `VP9Preset` are the concrete implementations of formats.
  Use them as example implementations if you need to.

### The preset configuration

These configurations allow you in multiple places to tweak the streams within a video/file.

You can define them globally for a specific stream type:
```php
<?php
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['video']['defaults'][\Hn\Video\Preset\H264Preset::class]['quality'] = 0.6;
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['video']['defaults'][\Hn\Video\Preset\AacPreset::class]['quality'] = 1.0;
```

You can define them within the format definition itself:

```php
<?php
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['video']['formats']['mp4'] = [
    'fileExtension' => 'mp4',
    'mimeType' => 'video/mp4',
    'video' => [\Hn\Video\Preset\H264Preset::class, ['quality' => 0.6]],
    'audio' => [\Hn\Video\Preset\AacPreset::class, ['quality' => 1.0]],
    'additionalParameters' => ['-movflags', '+faststart', '-map_metadata', '-1', '-f', 'mp4'],
];
```

You can define them on the default set of formats used. Here you target them by there type eg. video, audio:
```php
<?php
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['video']['default_video_formats']['mp4'] = [
    'video' => ['quality' => 0.6],
    'audio' => ['quality' => 1.0]
];
```

And you can define them within the media view helper similar to the definition above.
Note that by defining the `formats` key, the `default_video_formats` configurations is overridden. 

```html
<f:media file="{file}" additionalConfig="{formats: {mp4: {video: {quality: 0.6}, audio: {quality: 1.0}}}}" />
```

You can also define them within the view helper without overriding the format list
but you should limit yourself to common options like `width`, `height`, `quality` and `framerate` if you do that.

```html
<f:media file="{file}" additionalConfig="{video: {quality: 0.6}, audio: {quality: 1.0}}" />
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
