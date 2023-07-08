Dropzone.autoDiscover = false;
Dropzone.options.uploadZone = {
    acceptedFiles: 'image/*,application/pdf',
    dictDefaultMessage: 'Klik hier of drop je bestanden om te uploaden',
    dictFileTooBig: 'Dit bestand is {{filesize}}MB, dat is groter dan de maximale {{maxFilesize}}MB die wij toestaan',
    maxFilesize: 5,
}

$(function() {
    let year = new Date().getFullYear();
    let uploadZone = new Dropzone('#upload-zone');
    uploadZone.on('success', function(file, response) {
        let result_element = $('#upload_result');

        if ('.pdf' === response.substring(response.length - 4, response.length)) {
            result_element.html(
                result_element.html() + '<br /><br />[url=https://images.beneluxspoor.net/bnls_' + year + '/' + response + ']https://images.beneluxspoor.net/bnls_' + year + '/' + response + '[/url]'
            );
        } else {
            result_element.html(
                result_element.html() + '<br /><br />[url=https://images.beneluxspoor.net/bnls_' + year + '/' + response + '][img]https://images.beneluxspoor.net/bnls_' + year + '/' + response + '[/img][/url]'
            );
        }
    });
});
