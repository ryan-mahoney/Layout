# Separation.js 

## Separation.js is a mini browser-based and/or server based framework for templating partials in local files with external data sources.  It has both a Javascript and PHP implementation.

### Usage

This Javascript version of this component relies on [Handlebars.js](http://handlebarsjs.com/) and [jQuery](http://jquery.com/).  The PHP version uses [Handlebars.php](https://github.com/XaminProject/handlebars.php).

Basic browser-based usage:

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
Separation.js is driven by a javascript config file containing a call to the separation jQuery plugin that passes in JSON data.  Generally, if you have an HTML template called `example.html`, it would have a accompanying separation config file named `example-sep.js`.  This module operates on a page level.  Both the Javascript and PHP versions of the system are able to read the same `-sep.js` configuration file.

Separation.js is very simple.  For each item in the configuration, it attempts to read the data via XHR or JSONP.  Arguments can be passed to the remote JSON API server.  A Handlebar template for rendering a partial into the template must also be specified, and the CSS selector whose content will be replaced by the generated markup.

### Important

If you are using the PHP version, you will need to put the following script-like tag inside of each targetted container.  This script tag will never be executed, it is used by the PHP script as a place-holder that will be replaced upon execution.  The important aspect of using this tag, is that the `selector` attribute should match the selector specified in the config file.  Here is an example of the markup with the script tag:

```html
<div id="images"><script type="text/separation" selector="#images"></script></div>
```

### Motivation

MVC frameworks and other software design patterns that intend to facilitate (or force) a separation between business logic from display logic are ever increasing in popularity.  While this is a positive development in the industry, despite their wide adoption, often times projects using these patterns still fail to achieve a complete separation.  

For example, a front-end developer may be tasked with working on a `View`, but she still needs to access and review the `Model` and `Controller` to complete her work.  Projects that use Separation.js are forced to create JSON APIs for each data source, and the JSON itself acts as a reference to the data.  With the system, a front-end developer can completely work "in the front-end", working locally within HTML files without any need to access back-end code or upload files to a server.  

In production, the PHP version of Separation.js allows the configurations and templates to be re-used so that the final implementation can do all templating rendering on the server side.  