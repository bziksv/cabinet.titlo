(function ($) {
    function syncPhraseMode() {
        var checked = $('#cabinet-sm-phrase-search').is(':checked');
        var $fields = $('#cabinet-sm-phrase-fields');
        var $phrase = $('#phrase');
        if (checked) {
            $fields.show();
            $phrase.prop('required', true);
            $('#notification').addClass('d-none');
        } else {
            $fields.hide();
            $phrase.prop('required', false);
            $('#notification').removeClass('d-none');
        }
    }

    $(function () {
        $('#cabinet-sm-phrase-search').addClass('checkbox').on('change', syncPhraseMode);
        syncPhraseMode();
    });
}(window.jQuery));
