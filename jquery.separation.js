(function ($) {
    var Sep = {
        "Entities": {},
        "push": function (Entity) {
            Sep.Entities[Entity.attributes.id] = Entity;
        },
        "get": function (id) {
            return Sep.Entities[id];
        }
    };

    var Entity = Sep.Entity = function (attributes) {
        this.attributes = attributes || {};
        var entity = this;

        this.fetch = function () {
            var url = entity.attributes.url;
            var args = entity.attributes.args;
            if (entity.attributes.type !== undefined && entity.attributes.type == 'Collection') {
                var protocol = url.split('//', 2)[0];
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
                    qs = '?callback=?';
                }
                var pieces = url.replace(/.*?:\/\//g, "").split('/');
                url = '';
                $.each(['domain', 'path', 'collection', 'method', 'limit', 'skip', 'sort'], function (offset, key) {
                    if (entity.attributes.args[key] !== undefined) {
                        url += entity.attributes.args[key]
                    } else if (pieces[offset] !== undefined) {
                        url += pieces[offset];
                    } else {
                        return false;
                    }
                    url += '/';
                });
                url = protocol + '//' + url;
                url = url.substr(0, (url.length - 1)) + qs;
                console.log(url);
            }
            $.getJSON(url, args).done(function (data) {
                entity.data = data;
                if (entity.attributes.selector === undefined) {
                    return;
                }
                $(entity.attributes.selector).html(entity.template(data));
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

        if (this.attributes.hbs !== undefined) {
            $.ajax({
                url: entity.attributes.hbs,
                success: function (src) {
                    entity.template = Handlebars.compile(src);
                    if (entity.attributes.url !== undefined) {
                        entity.fetch();
                    }
                },
                dataType: 'text'
            });
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
        location.href = decodeURIComponent(location.href).replace(location.hash, "") + '#';
        var entity = Sep.get(obj.Sep);
        if (entity === undefined) {
            return;
        }
        if (obj.a !== undefined) {
            entity.set(obj.a);
        }
        entity.fetch();
    });

    $.fn.separation = function (config) {
        $(config).each(function (offset, partial) {
            if (partial.url === undefined || partial.hbs === undefined || partial.selector === undefined) {
                if (console) {
                    console.log('Skipped partial due to missing parameter, check url, hbs, or selector. Offset: ' + offset);
                    return;
                }
            }
            new Sep.Entity(partial);
        });
    };
}(jQuery));