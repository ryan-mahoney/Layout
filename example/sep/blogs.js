$(function() {
	$().separation([
		{
			"id": "blogs",
			"url": "http://collections.localhost/json/blogs/all/10/0/{\"display_date\":-1}",
			"args": {},
			"hbs": "../templates/blogs.hbs",
			"selector": "#content",
			"type": "Collection"
		}
	]);
});