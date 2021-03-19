<?php
// Rename this file to config.php

// Application-specific secret. Put a randomly genreated string here when you deploy it!
$_pepper = TODO generate this string

// This gets passed to "new mysqli($dbhost, $dbuser, $dbpass, $dbname)"
$_dbhost = 'p:127.0.0.1';
$_dbuser = 'mpgroceries';
$_dbpass = 'toor';
$_dbname = 'mpgroceries';

$_pwd_mem = 32768; // In KB it seems
$_pwd_threads = 4; // Number of CPU cores to use
$_pwd_time = 8;  // Hashing slowness. Can be set lower when more memory is used.

