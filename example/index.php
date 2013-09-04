<?php
require_once('../Separation.php');
Separation::config([
    'layouts'       => __DIR__ . '/layouts/',
    'templates'     => __DIR__ . '/templates/',
    'sep'           => __DIR__ . '/sep/'
]);
Separation::layout('blogs.html')->template()->write();