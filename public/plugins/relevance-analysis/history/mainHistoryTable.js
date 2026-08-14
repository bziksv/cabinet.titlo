$('#main_history_table').DataTable({
    "order": [[0, "desc"]],
    "pageLength": 10,
    "searching": true,
    dom: 'lBfrtip',
    buttons: [
        'copy', 'csv', 'excel'
    ],
    language: {
        lengthMenu: "_MENU_",
        search: "_INPUT_",
        paginate: {
            "first": "«",
            "last": "»",
            "next": "»",
            "previous": "«"
        },
    },
});

$(".dt-button").addClass('btn btn-secondary')

$('.repeat-scan-unique-sites').on('click', function () {
    $.ajax({
        type: "POST",
        dataType: "json",
        url: "/repeat-scan-unique-sites",
        data: {
            _token: $('meta[name="csrf-token"]').attr('content'),
            id: $(this).attr('data-target'),
        },
        success: function (response) {
            if (response.code === 200) {
                getSuccessMessage(response.message)
                $.each(response.object, function (key, value) {
                    $('#history-state-' + value).html(
                        typeof raHistoryProcessingHtml === 'function'
                            ? raHistoryProcessingHtml()
                            : '<div class="ra-hist-processing"><span class="ra-hist-processing__text">Обрабатывается..</span><span class="loader relevance-history-spinner" aria-hidden="true"></span></div>'
                    )
                })

            } else if (response.code === 415) {
                getErrorMessage(response.message)
            }
        },
    });
})

$('.start-through-analyse').on('click', function () {
    $.ajax({
        type: "POST",
        dataType: "json",
        url: "/start-through-analyse",
        data: {
            _token: $('meta[name="csrf-token"]').attr('content'),
            id: $(this).attr('data-target'),
        },
        success: function (response) {
            if (response.code === 200) {
                getSuccessMessage(response.message, 5000)
            } else if (response.code === 415) {
                getErrorMessage(response.message, 15000)
            }
        },
    });
})
