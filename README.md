# Separation.js 

## Separation.js is a mini browser-based and server based framework for templating partials in local or server files with external JSON data sources.  It has both a Javascript and PHP implementation.

### Usage

This Javascript version of this component relies on [Handlebars.js](http://handlebarsjs.com/) and [jQuery](http://jquery.com/).  The PHP version uses [Handlebars.php](https://github.com/XaminProject/handlebars.php).

Basic browser-based usage:

```html
<script src="http://cdnjs.cloudflare.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
<script src="http://cdnjs.cloudflare.com/ajax/libs/handlebars.js/1.0.0/handlebars.min.js"></script>
<script src="../js/jquery.separation.js"></script>

<script type="text/javascript">
$(function() {
	$().separation([
		{
			"id": "contact",
			"url": "http://separation.localhost/json-form/contact",
			"args": {},
			"hbs": "form-contact.hbs",
			"target": "content",
			"type": "Form"
		}
	]);
});
</script>
```

See the `example` folder for a working sample.  Things get interesting when you use the same Javascript config file and handlebar templates to generate the HTML on the server side with PHP.

PHP example:

```php
<?php
require_once('Separation.php');
Separation::layout('blogs.html')->template()->write();
```

### How it works
Separation.js is driven by a javascript config file containing a call to the separation jQuery plugin that passes in JSON data.  Generally, if you have an HTML template called `layouts/example.html`, it would have a accompanying separation config file named `sep/example.js`.  This module operates on a page level.  Both the Javascript and PHP versions of the system are able to read the same configuration file.

Separation.js is very simple.  For each item in the configuration, it attempts to read the data via JSONP.  Arguments can be passed to the remote JSON API server.  A Handlebar template for rendering a partial into the template must also be specified, for example `partials/example.hbs`. Finally, the main engine will substitute the contant into variable place holders in the layout file, `{{{content}}}`.

### Important

When you work locally, you will need to do the following:

``In Firefox``
In the address bar type in "about:config"
Set the "Security.fileuri.strict_origin_policy" to false

``In Chrome``
Load chrome with the "--allow-file-access-from-files" command line flag set

### Motivation

MVC frameworks and other software design patterns that intend to facilitate (or force) a separation between business logic from display logic are ever increasing in popularity.  While this is a positive development in the industry, despite their wide adoption, often times projects using these patterns still fail to achieve a complete separation between Model, Controllers and Views.

When you use Separation.js, Separation acts as a simple Controller.  Your JSON API must do all of the data-processing work, and your Handlabar files will renders logic-less Views.  These are limitations for sure, but positive limitations that enforce that things are dealt with within the "concern" that they trully belong to.

In production, the PHP version of Separation.js allows the configurations and templates to be re-used so that the final implementation can do all templating rendering on the server side.  