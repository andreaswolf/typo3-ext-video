import {FileModifier} from '@typo3/backend/uploader/modify-files-modal.js'
import { default as Modal, Sizes as ModalSizes } from '@typo3/backend/modal.js';
import { html } from 'lit';
import { SeverityEnum } from '@typo3/backend/enum/severity.js';
import '@typo3/backend/element/progress-bar-element.js';
import {Mp4Presets, Mp4Preset, createMp4File} from "./video-converter.js";

/**
 * Integration into the reworked drag uploader, currently WIP and only available on GitHub
 * (https://github.com/andreaswolf/typo3/tree/modify-files-before-upload)
 */
export class FFmpegVideoScaler implements FileModifier {
  public getButtonLabel(): string {
    return 'Scale video';
  }

  public getIdentifier(): string {
    return 'ffmpeg-video-scaler';
  }

  canHandleFile(file: File): boolean {
    return file.type.startsWith('video/');
  }

  async show(file: File): Promise<File> {
    return (new ScaleVideoModal(file)).draw();
  }
}

class ScaleVideoModal {
  private readonly ready: Promise<File>
  private resolve: (file: File) => void;

  constructor(private readonly file: File) {
    this.ready = new Promise((resolve) => {
      this.resolve = resolve;
    });
  }

  public draw(): Promise<File> {
    const modal = Modal.advanced({
      title: 'Convert video',
      content: html`
        <div class="modal-content-root">
          <p>Scale with preset:</p>
          <ul>
            ${Object.keys(Mp4Presets).map((name) =>
              html`<li>Preset ${name}: <button class="btn btn-default" @click="${() => scaleVideo(Mp4Presets[name])}">Konvertieren</button></li>`
            )}
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
    modal.addEventListener('button.clicked', (e: Event): void => {
      const button = e.target as HTMLButtonElement;
      if (button.name === 'cancel') {
        this.resolve(this.file);
        modal.hideModal();
      }
    });

    const scaleVideo = (preset: Mp4Preset) => {
      const list: HTMLUListElement = modal.querySelector('ul');
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
      const progressElement: HTMLElement|null = modal.querySelector('.video-progress-root');

      // TODO using typo3-backend-progress-bar currently fails due to "Sharing constructed stylesheets in multiple documents is not allowed"; probably because it is loaded twice?

      createMp4File(this.file, preset, (progress: number) => {
        if (progressElement !== null) {
          progressElement.innerHTML = `${Math.round(progress * 100)} %`;
        }
      })
        .then((resultFile: File) => {
          console.log('Successfully converted file');
          this.resolve(resultFile);
          modal.hideModal();
        })
    }

    return this.ready;
  }
}
