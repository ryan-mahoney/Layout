# Separation.js is a mini browser-based framework for rendering arrays of json data sources with handlebar to specific css selectors.

## Usage

This component relies on [Handlebars.js](http://handlebarsjs.com/) and [jQuery](http://jquery.com/).

Basic usage:

```
<script src="http://cdnjs.cloudflare.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
<script src="http://cdnjs.cloudflare.com/ajax/libs/handlebars.js/1.0.0/handlebars.min.js"></script>
<script src="./jquery.separation.js"></script>

<script type="text/javascript">
$(function() {
	$().separation([
		{
			'jsonUrl': "http://api.flickr.com/services/feeds/photos_public.gne?jsoncallback=?",
			'args': {tags: "mount rainier", tagmode: "any", format: "json"},
			'template': './flickr.hbs',
			'selector': '#images'
		}
	]);
});
</script>
```