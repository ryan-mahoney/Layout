<?php
require_once('../Separation.php');
Separation::config([
	'layouts' => __DIR__ . '/layouts/',
	'templates' => __DIR__ . '/layouts/'	
]);
Separation::layout('blogs.html')->template()->write();
