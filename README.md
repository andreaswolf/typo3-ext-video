# what does this extension do

- At the moment, this extension compresses videos during the upload process using a wasm version of ffmpeg.
  - This allows you to serve well compressed videos
  - It save storage space on your server by not uploading the original files

## future plans

- hook into the file upload process to create HLS video fragments
  - allows you to serve well compressed videos
  - can improve slow upload speeds with slow internet connections
  - avoid max upload size limits on your hoster
  - save storage space on your server by not uploading the original files
  - reliably serve your videos to clients with a bad connection
  - Cut videos by just modifying the playlist file. e.g. cut out the audio etc. Maybe even a tiny video editor in the backend.

Video compression is done within the browser so there is no server dependency for video compression.

## how to use

Just install it, nothing more to do as long as HSL isn't implemented.
All videos will be converted to `mp4` files during the upload process.
Existing Files are not touched.
The default html video tag will work well with serving those videos.

## important note

There are some headers required to enable multithreaded wasm binaries.
Those headers are `Cross-Origin-Opener-Policy` and `Cross-Origin-Embedder-Policy`.
You can read more here: https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/SharedArrayBuffer#security_requirements
These headers are set using a Middleware, so you likely don't need to do anything.
But this could have side effects if you... embed the typo3 backend in an iframe or something like that.
