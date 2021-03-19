<?php 
if ($_ok !== true) {
	die('Error 49');
}

if ($_SESSION['loggedin'] === 'yes') {
	if (isset($_GET['logout'])) {
		checkCSRF($_GET['csrf']);
		$_SESSION['loggedin'] = 'no';
		unset($_SESSION);
		session_destroy();
		die('You are now logged out. <a href="./">Log back in?</a>');
	}
	header('Location: ./');
	exit;
}

if (isset($_GET['secret'])) {
	$result = $db->query('SELECT id, username, secret FROM users WHERE secret = "' . $db->escape_string($_GET['secret']) . '"') or die('Database error 5091');
	if ($result->num_rows != 1) {
		die('Unknown secret');
	}
	$row = $result->fetch_row();
	$_SESSION['uid'] = $row[0];
	$_SESSION['username'] = $row[1];
	$_SESSION['secret'] = $row[2];
	$_SESSION['csrf'] = bin2hex(openssl_random_pseudo_bytes(8));
	$_SESSION['loggedin'] = 'yes';
	header("Location: ./");
	exit;
}

if (isset($_POST['newusername'])) {
	if (!empty($_POST['pwd2']) && $_POST['pwd'] != $_POST['pwd2']) {
		echo '<b style="color:red">Passwords do not match</b><br><br>';
	}
	else {
		$result = $db->query('SELECT id FROM users WHERE username = "' . $db->escape_string($_POST['newusername']) . '"') or die('Database error 23849');
		if ($result->num_rows != 0) {
			echo 'List name already taken<br><br>';
		}
		else {
			$pwd = hash_hmac('sha256', $_POST['pwd'], $_pepper);
			$pwd = password_hash($pwd, PASSWORD_ARGON2ID, ['memory_cost'=>$_pwd_mem, 'time_cost'=>$_pwd_time, 'threads'=>$_pwd_threads]);

			$secret = substr(hash('sha256', openssl_random_pseudo_bytes(12)), 0, 14);

			$db->query('INSERT INTO users (username, pwd, secret) VALUES("' . $db->escape_string($_POST['newusername']) . '", "' . $db->escape_string($pwd) . '", "' . $secret . '")') or die('Database error 184973');

			$_SESSION['loggedin'] = 'yes';
			$_SESSION['uid'] = $db->insert_id;
			$_SESSION['csrf'] = bin2hex(openssl_random_pseudo_bytes(8));
			$_SESSION['username'] = $_POST['newusername'];
			$_SESSION['secret'] = $secret;

			header('Location: ./?firstuse');
			exit;
		}
	}
}

if (isset($_POST['existingusername'])) {
	$result = $db->query('SELECT id, username, secret, pwd FROM users WHERE username = "' . $db->escape_string($_POST['existingusername']) . '"') or die('Database error 72834');
	if ($result->num_rows == 1) {
		$result = $result->fetch_row();
		$pwd = hash_hmac('sha256', $_POST['pass'], $_pepper);
		if (password_verify($pwd, $result[3])) {
			$_SESSION['loggedin'] = 'yes';
			$_SESSION['csrf'] = bin2hex(openssl_random_pseudo_bytes(8));
			$_SESSION['uid'] = $result[0];
			$_SESSION['username'] = $result[1];
			$_SESSION['secret'] = $result[2];

			header('Location: ./');
			exit;
		}
	}
	echo "<b style='color:red'>Incorrect list name or password</b><br><br>";
}

?>
<strong>This is a grocery list with a few too many features. Feel free to explore.</strong><br>
The source code and more information is available on <a href="https://github.com/lgommans/mpgroceries">github.com/lgommans/mpgroceries</a>.<br><br>

You are not logged in to any list.<br><br>

Log in:
<form method=POST>
	<input type=hidden name=csrf value=<?php echo $_SESSION['csrf']; ?>>

	List name: <input name=existingusername><br>
	Password: <input type=password name=pass><br>
	<input type=submit value='Login'>
</form>
<br>

Or create a new list (step 1 of 1):
<form method=POST>
	<input type=hidden name=csrf value=<?php echo $_SESSION['csrf']; ?>>

	List name: <input name=newusername maxlength=50> (your username, sort of)<br>
	Password: <input type=password name=pwd maxlength=900> (stored as peppered argon2id)<br>
	Repeat password: <input type=password name=pwd2> (optional, but if you enter this then it will be checked)<br>
	<input type=submit value='Create list'>
</form>

<script>
	document.title = 'Login - ' + document.title;
</script>

