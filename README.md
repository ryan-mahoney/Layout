# Separation.js 

## Separation.js is a mini browser-based framework for templating partials in local files with external data sources.  It can also be run server side in PHP, using the same configuration and template files.

### Usage

This Javascript version of this component relies on [Handlebars.js](http://handlebarsjs.com/) and [jQuery](http://jquery.com/).  The PHP version uses [Handlebars.php](https://github.com/XaminProject/handlebars.php).

Basic usage:

```html
<script src="http://cdnjs.cloudflare.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
<script src="http://cdnjs.cloudflare.com/ajax/libs/handlebars.js/1.0.0/handlebars.min.js"></script>
<script src="./jquery.separation.js"></script>

<script type="text/javascript">
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
</script>
```

See the `example.html` file for a more accurate depiction.  Things get interesting when you use the same Javascript config file and handlebar templates to generate the HTML on the server side with PHP.

PHP example:

```php
<?php
require_once('Separation.php');
Separation::html('example.html')->template()->write();
```

### How it works
Separation.js is driven by a config file.  Generally, if you have an HTML template called `example.html`, it would have a accompanying separation config file named `example-sep.js`.  Both the Javascript and PHP versions of the system are able to read this file and operate on it.

Separation.js is very simple.  For each item in the config, it will attempt to read the data via XHR or JSONP.  Arguments can be passed to the remote server.  A handlebar template for a partial must be specified, and a CSS selector for where to render the final markup into within the original template.

### Important

If you are also using the PHP version, you will need to put the following script-like tag into you HTML as a stub that will get replaced by the PHP script:

```html
<script type="text/separation" selector="#images"></script>
```

This script tag will never be interpreted.  it is always replaced by either the PHP or Javascript logic.  The important thing is that the selector attribute should match the selector specified in the config file.