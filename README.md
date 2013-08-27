# Separation.js 

## Separation.js is a mini browser-based framework for templating partials in local files with external data sources.  It can also be run server side in PHP.

### Usage

This component relies on [Handlebars.js](http://handlebarsjs.com/) and [jQuery](http://jquery.com/).

Basic usage:

```html
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

Things get interesting when you can use the same javascript config file and handlebar templates to generate the HTML on the server side.

PHP example:

```php
<?php
require_once('Separation.php');
Separation::html('example.html')->template()->write();
```

### How it works
Separation.js is driven by a config file.  Generally, if you have an HTML template called example.html, it would have a accompanying separation config file named example-sep.js.  Both the javascript and php versions of the system are able to read this file and operate on it.

Separation.js is very simple.  For each item in the config, it will attempt to read the data via XHR or JSONP.  Arguments can be passed to the remote server.  A tepmplate for a "partial" must be specified, and a CSS selector for where to render the final markup into.

If you are also using the PHP version, you will need to put the following script-like tag into you HTML as a stub that will get replaced by the PHP script:

```html
<script type="text/separation" selector="#images"></script>
```

This script will never be interpreted.  it is always replaced by either PHP or Javascript.  The important thing is that the selector attribute should match the selector specified in the config file.