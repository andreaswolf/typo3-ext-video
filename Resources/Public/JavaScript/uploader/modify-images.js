import { default as Modal, Sizes as ModalSizes } from '@typo3/backend/modal.js';
import { html } from 'lit';
import { SeverityEnum } from '@typo3/backend/enum/severity.js';
export class CropScaleImages {
    canHandleFile(file) {
        const extension = file.name.split('.').pop();
        return CropScaleImages.SUPPORTED_IMAGE_EXTENSIONS.indexOf(extension) > -1;
    }
    getButtonLabel() {
        return 'Crop/Scale';
    }
    getIdentifier() {
        return 'crop-scale-images';
    }
    async show(file) {
        return (new CropScaleModal(file)).draw();
    }
}
CropScaleImages.SUPPORTED_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'bmp'];
class CropScaleModal {
    constructor(file) {
        this.file = file;
        this.ready = new Promise((resolve) => {
            this.resolve = resolve;
        });
    }
    draw() {
        const modal = Modal.advanced({
            title: 'Crop / scale image',
            content: html `<p>Test</p>`,
            severity: SeverityEnum.info,
            buttons: [
                {
                    text: TYPO3.lang['file_upload.button.cancel'] || 'Cancel',
                    active: true,
                    btnClass: 'btn-default',
                    name: 'cancel',
                },
                {
                    text: TYPO3.lang['file_upload.button.continue'] || 'Continue with selected actions',
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
                this.resolve(this.file);
                modal.hideModal();
            }
            else if (button.name === 'continue') {
                // TODO resolve new file
                setTimeout(() => this.resolve(this.file), 1000);
                modal.hideModal();
            }
        });
        return this.ready;
    }
}
