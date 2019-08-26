<?php 
	$_ok = true;

	session_start();

	require('config.php');
	require('db.php');
	require('functions.php');

	if (!isset($_GET['admin'])) {
		require('processRequests.php');
	}
?>
<!DOCTYPE html>
<meta charset='utf-8'/>
<title>Multiplayer Grocery List</title>
<meta name=viewport content="width=200, initial-scale=1"/>

<?php 
	if ($_SESSION['loggedin'] !== 'yes' || isset($_GET['logout'])) {
		require('login.php');
	}
	// The login page may change the state, so if() again.
	if ($_SESSION['loggedin'] === 'yes') {
		if (isset($_GET['admin'])) {
			require('admin.php');
			exit;
		}
		if (isset($_GET['recipes'])) {
			require('recipes.php');
			exit;
		}
		if (isset($_GET['map'])) {
			require('map.php');
			exit;
		}
		if (isset($_GET['combinations'])) {
			require('combinations.php');
			exit;
		}
		require('loggedin.php');
	}

