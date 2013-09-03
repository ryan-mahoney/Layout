$(function() {
	$().separation([
		{
			"id": "blogs",
			"url": "http://collections.localhost/json/blogs",
			"args": {},
			"hbs": "../templates/blog.hbs",
			"selector": "#content",
			"type": "Document"
		}
	]);
});