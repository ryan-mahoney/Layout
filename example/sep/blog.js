$(function() {
	$().separation([
		{
			"id": "blogs",
			"url": "http://collections.localhost/json/blogs/bySlug/:slug",
			"args": {},
			"hbs": "../templates/blog.hbs",
			"selector": "#content",
			"type": "Document"
		}
	]);
});