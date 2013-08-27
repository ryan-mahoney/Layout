(function ($) {
    $.fn.separation = function (config) {
        $(config).each(function (offset, partial) {
            if (partial.jsonUrl === 'undefined' || partial.templateName === 'undefined' || partial.selector === 'undefined') {
                if (console) {
                    console.log('Skipped partial due to missing parameter, check jsonUrl, or templateName, or selector');
                    return;
                }
            }
            $.getJSON(partial.jsonUrl, partial.args).done(function (data) {
                $.ajax({
                    url: partial.template,
                    success: function (src) {
                        $(partial.selector).html(Handlebars.compile(src)(data));
                    },
                    dataType: 'text'
                });
            });
        });
    };
}(jQuery));