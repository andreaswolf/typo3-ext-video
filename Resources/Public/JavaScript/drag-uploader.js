import $ from "jquery";
import {createHlsFiles, createMp4File} from "./video-converter.js";

// export the original module
// this also loads and initializes it
export * from "@hn/video/typo3/backend/drag-uploader.js";

const ORIGINAL_FILE = Symbol("original file");

// monkey patch the processFiles method of the DragUploaderPlugin to replace video files with a fake m3u8 file
// the upload process can than later be extended to convert the video to a m3u8 file
const origDragUploader = $.fn.dragUploader;
$.fn.dragUploader = function () {
    const result = origDragUploader.apply(this, arguments);
    this.each(function () {
        // https://github.com/TYPO3/typo3/blob/fab5b85d9a980a4f33c72f8a584b90141908f0d5/Build/Sources/TypeScript/backend/drag-uploader.ts#L338
        const uploaderPlugin = $(this).data("DragUploaderPlugin");
        const origProcessFiles = uploaderPlugin.processFiles;
        uploaderPlugin.processFiles = function (files) {
            const modifiedFiles = Array.from(files)
                .map((file) => {
                    if (!file.type.startsWith("video/")) {
                        return file;
                    }

                    // if a video is uploaded, replace it with a fake m3u8 file
                    // this file will be generated during the upload process
                    // const replacementFileName = file.name.replace(/\.[^.]{2,4}$|$/, ".m3u8");
                    // const replacementFile = new File([""], replacementFileName, {type: "application/x-mpegURL"});
                    const replacementFileName = file.name.replace(/\.[^.]{2,4}$|$/, ".mp4");
                    const replacementFile = new File([""], replacementFileName, {type: "video/mp4"});
                    replacementFile[ORIGINAL_FILE] = file[ORIGINAL_FILE] ?? file;
                    return replacementFile;
                })
            return origProcessFiles.call(this, modifiedFiles);
        };
    });
    return result;
}

// monkey patch the XMLHttpRequest send method to intercept the upload process
// if the uploaded file is the fake m3u8 file, convert the original video file to a m3u8 file
// https://github.com/TYPO3/typo3/blob/fab5b85d9a980a4f33c72f8a584b90141908f0d5/Build/Sources/TypeScript/backend/drag-uploader.ts#L730
const origSend = XMLHttpRequest.prototype.send;
XMLHttpRequest.prototype.send = function (data) {
    if (!(data instanceof FormData) || !data.has('upload_1')) {
        return origSend.call(this, data);
    }

    const file = data.get('upload_1');
    if (!(file instanceof File) || !(ORIGINAL_FILE in file)) {
        return origSend.call(this, data);
    }

    const conversion = createMp4File(file[ORIGINAL_FILE], (progress) => {
        const event = new Event("progress");
        event.loaded = Math.floor(progress * 1000);
        event.total = 1000;
        this.upload.dispatchEvent(event);
    });

    conversion
        .then(async (mp4File) => {
            // finally start the actual upload of the m3u8 file
            data.set('upload_1', mp4File);
            origSend.call(this, data);
        })
        .catch((error) => {
            console.error(error);
            // TODO throw error event
        });
};

// XMLHttpRequest.prototype.send = function (data) {
//     if (!(data instanceof FormData) || !data.has('upload_1') || !data.has('data[upload][1][target]')) {
//         return origSend.call(this, data);
//     }
//
//     const file = data.get('upload_1');
//     const location = data.get('data[upload][1][target]');
//     if (!(file instanceof File) || !(ORIGINAL_FILE in file)) {
//         return origSend.call(this, data);
//     }
//
//     let filesEmitted = 0, filesUploaded = 0, runningUploads = new Set();
//     const conversion = createHlsFiles(
//         file[ORIGINAL_FILE],
//         (progress) => {
//             const event = new Event("progress");
//             event.loaded = Math.floor(progress * 1000);
//             event.total = 1000;
//             this.upload.dispatchEvent(event);
//         },
//         (file) => {
//             filesEmitted++;
//             const upload = uploadFile(file, location).then(() => {
//                 filesUploaded++;
//                 runningUploads.delete(upload);
//             });
//             runningUploads.add(upload);
//         });
//
//     conversion
//         .then(async (m3u8File) => {
//             // the conversion is now done, so emit now progress events for the remaining file uploads
//             while (runningUploads.size > 0) {
//                 await Promise.race(runningUploads);
//                 const event = new Event("progress");
//                 event.loaded = filesUploaded;
//                 event.total = filesEmitted;
//                 this.upload.dispatchEvent(event);
//             }
//
//             // finally start the actual upload of the m3u8 file
//             data.set('upload_1', m3u8File);
//             origSend.call(this, data);
//         })
//         .catch((error) => {
//             console.error(error);
//             // TODO throw error event
//         });
// };
//
// function uploadFile(file, location) {
//     return new Promise((resolve, reject) => {
//         const formData = new FormData();
//         formData.append('data[upload][1][target]', location);
//         formData.append('data[upload][1][data]', '1');
//         formData.append('overwriteExistingFiles', "replace");
//         formData.append('redirect', '');
//         formData.append('upload_1', file);
//
//         const xhr = new XMLHttpRequest();
//         xhr.onreadystatechange = () => {
//             if (xhr.readyState !== 4) {
//                 return;
//             }
//             if (xhr.status === 200) {
//                 resolve(xhr.responseText);
//             } else {
//                 reject(new Error(xhr.statusText));
//             }
//         };
//         xhr.open('POST', TYPO3.settings.ajaxUrls.file_process);
//         xhr.send(formData);
//     });
// }