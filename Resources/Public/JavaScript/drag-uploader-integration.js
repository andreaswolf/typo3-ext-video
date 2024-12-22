import { default as Modal, Sizes as ModalSizes } from '@typo3/backend/modal.js';
import { html } from 'lit';
import { SeverityEnum } from '@typo3/backend/enum/severity.js';
import '@typo3/backend/element/progress-bar-element.js';
import { Mp4Presets, createMp4File } from "./video-converter.js";
/**
 * Integration into the reworked drag uploader, currently WIP and only available on GitHub
 * (https://github.com/andreaswolf/typo3/tree/modify-files-before-upload)
 */
export class FFmpegVideoScaler {
    getButtonLabel() {
        return 'Scale video';
    }
    getIdentifier() {
        return 'ffmpeg-video-scaler';
    }
    canHandleFile(file) {
        return file.type.startsWith('video/');
    }
    async show(file) {
        return (new ScaleVideoModal(file)).draw();
    }
}
class ScaleVideoModal {
    file;
    ready;
    resolve;
    constructor(file) {
        this.file = file;
        this.ready = new Promise((resolve) => {
            this.resolve = resolve;
        });
    }
    draw() {
        const modal = Modal.advanced({
            title: 'Convert video',
            content: html `
        <div class="modal-content-root">
          <p>Scale with preset:</p>
          <ul>
            ${Object.keys(Mp4Presets).map((name) => html `<li>Preset ${name}: <button @click="${() => scaleVideo(Mp4Presets[name])}">Konvertieren</button></li>`)}
          </ul>
        </div>
      `,
            severity: SeverityEnum.info,
            buttons: [
                {
                    text: TYPO3.lang['file_upload.button.cancel'] || 'Cancel',
                    active: true,
                    btnClass: 'btn-default',
                    name: 'cancel',
                },
            ],
            additionalCssClasses: ['modal-inner-scroll'],
            size: ModalSizes.medium,
        });
        modal.addEventListener('button.clicked', (e) => {
            const button = e.target;
            if (button.name === 'cancel') {
                this.resolve(this.file);
                modal.hideModal();
            }
        });
        const scaleVideo = (preset) => {
            const list = modal.querySelector('ul');
            const root = list.parentElement;
            if (root === null) {
                console.error('No modal root found');
                return;
            }
            list.remove();
            const progressTable = `<div>
        <table>
          <tbody>
          <tr>
            <td>Fortschritt:</td>
            <td class="video-progress-root">0 %</td>
          </tr>
          </tbody>
        </table>
      </div>`;
            modal.querySelector('.modal-content-root').innerHTML = progressTable;
            const progressElement = modal.querySelector('.video-progress-root');
            // TODO using typo3-backend-progress-bar currently fails due to "Sharing constructed stylesheets in multiple documents is not allowed"; probably because it is loaded twice?
            createMp4File(this.file, preset, (progress) => {
                if (progressElement !== null) {
                    progressElement.innerHTML = `${Math.round(progress * 100)} %`;
                }
            })
                .then((resultFile) => {
                console.log('Successfully converted file');
                this.resolve(resultFile);
                modal.hideModal();
            });
        };
        return this.ready;
    }
}
