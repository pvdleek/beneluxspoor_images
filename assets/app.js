import './styles/app.css';
import './../vendor/enyo/dropzone/dist/dropzone.css'

import './bootstrap';

import './../vendor/enyo/dropzone/dist/dropzone'

Dropzone.autoDiscover = false;
Dropzone.options.uploadZone = {
    acceptedFiles: 'image/*,application/pdf',
    dictDefaultMessage: 'Klik hier of drop je bestanden om te uploaden',
    dictFileTooBig: 'Dit bestand is {{filesize}}MB, dat is groter dan de maximale {{maxFilesize}}MB die wij toestaan',
    maxFilesize: 5,
}

$(function() {
    let uploadZone = new Dropzone('#upload-zone');
    uploadZone.on('success', function(file, response) {
        let result_element = $('#upload_result');

        if ('.pdf' === response.substring(response.length - 4, response.length)) {
            result_element.html(
                result_element.html() + '<br /><br />[url=https://images.beneluxspoor.net/bnls/' + response + ']https://images.beneluxspoor.net/bnls/' + response + '[/url]'
            );
        } else {
            result_element.html(
                result_element.html() + '<br /><br />[url=https://images.beneluxspoor.net/bnls/' + response + '][img]https://images.beneluxspoor.net/bnls/' + response + '[/img][/url]'
            );
        }
    });
});
