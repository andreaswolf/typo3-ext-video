import DragUploader from "./drag-uploader.js";
export * from "./drag-uploader.js";
export default DragUploader

import { FFmpegVideoScaler } from './drag-uploader-integration.js'
import { ModifierRegistry } from '@typo3/backend/uploader/modify-files-modal.js';

// TODO this should be possible w/o overriding a core module, since that makes it impossible to provide more than one
//      such module
ModifierRegistry.modifiers.push(new FFmpegVideoScaler);
