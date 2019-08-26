<?php 
if ($_ok !== true) {
	die('Error 49');
}

$db = new mysqli($_dbhost, $_dbuser, $_dbpass, $_dbname);
$db->query('SET CHARACTER SET utf8');
if ($db->connect_error) {
	die('Database connect error');
}

unset($_dbhost);
unset($_dbuser);
unset($_dbpass);
unset($_dbname);

