$(function() {
	$().separation([
		{
			"jsonUrl": "http://api.flickr.com/services/feeds/photos_public.gne?jsoncallback=?",
			"jsonArgs": {"tags": "mount rainier", "tagmode": "any", "format": "json"},
			"template": "./flickr.hbs",
			"selector": "#images"
		}
	]);
});