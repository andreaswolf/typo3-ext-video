# what does this extension do

- hooks into the file upload process to create HLS video fragments
  - allows you to serve well compressed videos
  - can improve slow upload speeds with slow internet connections
  - avoid max upload size limits on your hoster
  - save storage space on your server by not uploading the original files

Video compression is done within the browser so there is no server dependency for video compression.

# setup dev environment
```bash
# install dependencies
composer install
# setup typo3
vendor/bin/typo3 setup
# start dev server
php -S localhost:3000 -t public
```