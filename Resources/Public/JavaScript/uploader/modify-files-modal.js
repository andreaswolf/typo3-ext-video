import { SeverityEnum } from '@typo3/backend/enum/severity.js';
import { default as Modal, Sizes as ModalSizes } from '@typo3/backend/modal.js';
import { html, render } from 'lit';
import { CropScaleImages } from '@typo3/backend/uploader/modify-images.js';
import { repeat } from 'lit/directives/repeat.js';
import DragUploader from '@typo3/backend/drag-uploader.js';
export class ModifyFilesModal {
    constructor(files) {
        this.files = files;
        this.ready = new Promise((resolve) => {
            this.resolve = resolve;
        });
    }
    draw() {
        const filesAndModifiers = this.files
            .map((file) => {
            const modifiers = ModifierRegistry.modifiers.filter((modifier) => modifier.canHandleFile(file));
            return [file, modifiers];
        });
        const modal = Modal.advanced({
            title: 'Modify files before upload',
            content: html `
        <p>Modify files before upload</p>
        <table class="table">
          <thead>
          <tr>
            <th></th>
            <th>Dateiname</th>
            <th>Actions</th>
          </tr>
          </thead>
          <tbody>
            ${repeat(filesAndModifiers, (i) => i[0].name, (_, index) => html `<tr data-file-index="${index}">
                </td>
            </tr>`)}
          </tbody>
        </table>`,
            severity: SeverityEnum.info,
            buttons: [
                {
                    text: TYPO3.lang['file_upload.button.cancel'] || 'Cancel',
                    active: true,
                    btnClass: 'btn-default',
                    name: 'cancel',
                },
                {
                    text: 'Upload files',
                    btnClass: 'btn-warning',
                    name: 'continue',
                },
            ],
            additionalCssClasses: ['modal-inner-scroll'],
            size: ModalSizes.large,
        });
        modal.addEventListener('button.clicked', (e) => {
            const button = e.target;
            if (button.name === 'cancel') {
                this.resolve(null);
                modal.hideModal();
            }
            else if (button.name === 'continue') {
                this.resolve(this.files);
                modal.hideModal();
            }
        });
        // TODO this structure is a bit convoluted, check if we can somehow restructure this
        // eslint-disable-next-line prefer-const
        let renderRecordRow;
        const recordRowTemplate = (file, modifiers, index) => html `
      <td>${this.renderPreview(file)}</td>
      <td>${file.name} (${DragUploader.fileSizeAsString(file.size)})</td>
      <td>${modifiers.map((modifier) => html `
      <button
        class="btn btn-default"
        @click="${async () => modifier.show(file).then((newFile) => {
            this.files[index] = newFile;
            filesAndModifiers[index] = [newFile, filesAndModifiers[index][1]];
            renderRecordRow(newFile, modifiers, index);
        })}"
      >${modifier.getButtonLabel()}</button>
    `)}`;
        renderRecordRow = (file, modifiers, index) => {
            {
                const recordRow = modal.querySelector(`tr[data-file-index="${index}"]`);
                if (recordRow === null) {
                    console.error(`Could not find row for element ${index}`);
                    return;
                }
                render(recordRowTemplate(file, modifiers, index), recordRow);
            }
        };
        modal.addEventListener('typo3-modal-show', () => {
            filesAndModifiers.forEach(([file, modifiers], index) => renderRecordRow(file, modifiers, index));
        });
        return this.ready;
    }
    renderPreview(file) {
        switch (file.type) {
            case 'image/jpeg':
            case 'image/png':
                return html `<img src="${URL.createObjectURL(file)}" />`;
            default:
                return html `No preview available`;
        }
    }
}
class ModifierRegistryImpl {
    constructor() {
        this.modifiers = [
            new CropScaleImages()
        ];
    }
}
export const ModifierRegistry = new ModifierRegistryImpl();
