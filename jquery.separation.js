(function ($) {
    var SepDepth = 0;
    if (window.location.protocol == 'file:') {
        SepDepth = 1;
    } else {
        SepDepth = (window.location.pathname.match(/\//g)||[]).length - 1;
    }
    var Sep = {
        "Entities": {},
        "push": function (Entity) {
            Sep.Entities[Entity.attributes.id] = Entity;
        },
        "get": function (id) {
            return Sep.Entities[id];
        },
        "render": function () {
            var context = {};
            $.each(Sep.Entities, function (key, partial) {
                context[partial.attributes.id] = partial.markup;
            });
            layoutTemplate = Handlebars.compile($('body').html());
            $('body').html(layoutTemplate(context));
        }
    };

    var Entity = Sep.Entity = function (attributes, last) {
        this.attributes = attributes || {};
        var entity = this;

        this.fetch = function () {
            var url = entity.attributes.url;
            var args = entity.attributes.args;
            if (entity.attributes.type !== undefined) {
                if (entity.attributes.type == 'Collection') {
                    var protocol = url.split('://', 2)[0];
                    if (protocol == '' || protocol === undefined) {
                        protocol = window.location.protocol;
                    }
                    var tmp = url.split('?');
                    url = tmp[0];
                    var qs = '';
                    if (tmp.length > 1) {
                        for (var i=1; i < tmp.length; i++) {
                            qs += '?' + tmp[i];
                        }
                    } else {
                        qs = '?callback=?&Sep-local';
                    }
                    var pieces = url.replace(/.*?:\/\//g, "").split('/');
                    url = '';
                    $.each(['domain', 'path', 'collection', 'method', 'limit', 'page', 'sort'], function (offset, key) {
                        if (entity.attributes.args[key] !== undefined) {
                            url += entity.attributes.args[key];
                        } else if (pieces[offset] !== undefined) {
                            url += pieces[offset];
                        } else {
                            return false;
                        }
                        url += '/';
                    });
                    url = protocol + '://' + url;
                    url = url.substr(0, (url.length - 1)) + qs;
                } else if (entity.attributes.type == 'Document' && entity.attributes.args['id'] !== undefined) {
                    url = url.replace('/bySlug/:slug', '/byId/' + entity.attributes.args['id']) + '?callback=?';
                } else {
                    url += '?callback=?';
                }
            }
            $.getJSON(url, args).done(function (data) {
                entity.data = data;
                entity.markup = entity.template(data);
                if (last) {
                    Sep.render();
                }
            });
        };

        this.set = function (attributes) {
            if (entity.attributes.args === undefined) {
                entity.attributes.args = {};
            }
            for(var propt in attributes) {
                entity.attributes.args[propt] = attributes[propt];
            }
        };

        Sep.push(entity);

        if (entity.attributes.hbs !== undefined) {
            var base = repeat('../', SepDepth) + 'partials/';
            $.ajax({
                url: base + entity.attributes.hbs,
                success: function (src) {
                    entity.template = Handlebars.compile(src);
                    if (entity.attributes.url !== undefined) {
                        entity.fetch();
                    }
                },
                'error': function (qXHR, textStatus, errorThrown) {
                    console.log(entity.attributes.hbs);
                    console.log(qXHR);
                    console.log(textStatus);
                },
                dataType: 'text'
            });
        }

        function repeat(pattern, count) {
            if (count < 1) return '';
            var result = '';
            while (count > 0) {
                if (count & 1) result += pattern;
                count >>= 1, pattern += pattern;
            }
            return result;
        }
    };

    $(window).hashchange(function() {
        var json = location.hash.substr(1);
        if (json.substr(0, 1) != '{') {
            return;
        }
        try {
            var obj = jQuery.parseJSON(json);
        } catch (Exception) {
            return;
        }
        if (obj.Sep === undefined) {
            return;
        }
        //location.href = decodeURIComponent(location.href).replace(location.hash, "") + '#';
        var entity = Sep.get(obj.Sep);
        if (entity === undefined) {
            return;
        }
        if (obj.a !== undefined) {
            entity.set(obj.a);
        }
        entity.fetch();
    });
    window.onload = function() { $(window).hashchange(); };

    $.fn.separation = function (config) {
        if (window.location.protocol == 'file:') {
            $(config).each(function (offset, partial) {
                var last = false;
                if (offset + 1 == config.length) {
                    last = true;
                }
                if (partial.url === undefined || partial.hbs === undefined || partial.id === undefined) {
                    if (console) {
                        console.log('Skipped partial due to missing parameter, check url, hbs, or id. Offset: ' + offset);
                        return;
                    }
                }
                new Sep.Entity(partial, last);
            });
        }
    };
    
    $('form[data-xhr="true"]').ajaxForm({
        type: 'post',
        dataType: 'json',
        success: function (response, status, xhr, form) {
            var idFieldName = response['marker'] + '[id]';
            if (response['id'] && form.find('input[name="' + idFieldName + '"]').length == 0) {
                form.append('<input type="hidden" name="' + idFieldName + '" value="' + response['id'] + '" />');
            }
        }
    });

    $('body').on('click', '.sep-page', function () {
        var page = $(this).attr('data-page');
        var sep = $(this).attr('data-sep');
        var hash = '{"Sep":"' + sep + '", "a": {"page":' + page + '}}';
        window.location.hash = hash;
        $(this).attr('href', '#' + hash);
    });

}(jQuery));