<?php 
if ($_ok !== true) die('Error 49');

if (isset($_GET['resetSecret'])) {
	$secret = substr(hash('sha256', openssl_random_pseudo_bytes(12)), 0, 14);
	$_SESSION['secret'] = $secret;
	$db->query("UPDATE users SET secret = '$secret' WHERE id = $_SESSION[uid]") or die('Database error 9259');

	header('Location: ?admin');
	exit;
}

if (isset($_GET['resetCounter'])) {
	$item = $db->escape_string($_GET['resetCounter']);
	$db->query("UPDATE popularitems SET frequency = 0 WHERE uid = $_SESSION[uid] AND item = '$item'") or die('Database error 495810');

	header('Location: ?admin');
	exit;
}

if (isset($_GET['setCategory'])) {
	// Set the category for an item
	$itemid = intval($_GET['itemid']);
	$categoryid = intval($_GET['setCategory']);
	$db->query("UPDATE popularitems SET categoryid = $categoryid WHERE id = $itemid AND uid = $_SESSION[uid]") or die('Database error 5820940');

	header('Location: ?admin');
	exit;
}

if (isset($_POST['category'])) {
	// Add category
	$name = $db->escape_string($_POST['category']);
	$db->query("INSERT INTO categories (uid, name) VALUES($_SESSION[uid], '$name')") or die('Database error 15253405');

	header('Location: ?admin');
	exit;
}

if (isset($_GET['rmcat'])) {
	// Remove a category altogether
	$id = intval($_GET['rmcat']);

	$db->query("UPDATE popularitems SET categoryid = -1 WHERE uid = $_SESSION[uid] AND categoryid = $id") or die('Database error 5384202094');
	$db->query("DELETE FROM categories WHERE uid = $_SESSION[uid] AND id = $id") or die('Database error 16527959');

	header('Location: ?admin');
	exit;
}

if (isset($_GET['merge'])) {
	if (!isset($_GET['target'])) {
		?>
		<form>
			Selected item: <input name=merge value="<?php echo htmlspecialchars($_GET['merge'], ENT_COMPAT | ENT_HTML401, 'UTF-8'); ?>"><br>
			Merge into which item: <input name=target><br>
			<input type=submit name=admin value=Merge>
		</form>
		<?php 
		exit;
	}

	$merge = $db->escape_string($_GET['merge']);
	$targetitem = $db->escape_string($_GET['target']);

	$freq = $db->query("SELECT frequency FROM popularitems WHERE item = '$merge' AND uid = $_SESSION[uid]") or die('Database error 152819');
	if ($freq->num_rows != 1) die('Item 1 (selected item) not found.');
	$freq = $freq->fetch_row()[0];

	$targetid = $db->query("SELECT id FROM popularitems WHERE item = '$targetitem' AND uid = $_SESSION[uid]") or die('Database error 852109');
	if ($targetid->num_rows != 1) die('Item 2 (target / merge into) not found.');
	$targetid = $targetid->fetch_row()[0];

	$db->query("UPDATE popularitems SET frequency = frequency + $freq
		WHERE id = '$targetid' AND uid = $_SESSION[uid]") or die('Database error 14423');
	$db->query("DELETE FROM popularitems WHERE uid = $_SESSION[uid] AND item = '$merge'") or die('Database error 98525893');

	header('Location: ?admin');
	exit;
}

if (isset($_GET['remove'])) {
	$id = intval($_GET['remove']);
	$db->query("DELETE FROM popularitems WHERE uid = $_SESSION[uid] AND id = $id") or die('Database error 495810');

	header('Location: ?admin');
	exit;
}

$url = (($_SERVER["HTTPS"] == "off" || empty($_SERVER["HTTPS"])) ? "http://" : "https://") . $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"];
$secretUrl = str_replace('?' . $_SERVER['QUERY_STRING'], '', $url) . '?secret=' . $_SESSION['secret'];

?>
<script>
	function rm(item) {
		location = "?admin&remove=" + escape(item);
	}

	function resetCounter(item) {
		location = "?admin&resetCounter=" + escape(item);
	}

	function merge(item) {
		location = '?admin&merge=' + escape(item);
	}

	function helpSecret(btn) {
		document.getElementById("helpSecretText").innerText = "You can add this link to your bookmarks or favorites, so that you can open your grocery list without having to log in. "
			+ "This is especially useful on mobile devices. Some browsers allow you to add website shortcuts to your homescreen.";
		btn.parentNode.removeChild(btn);
	}

	function rmcat(id) {
		location = '?admin&rmcat=' + id;
	}

	document.title = 'Manage - ' + document.title
</script>
<input type=button value="Back to list" onclick='location="./";'><br><br>
Your list's name: <?php echo htmlentities($_SESSION['username'], ENT_COMPAT | ENT_HTML401, 'UTF-8'); ?><br>
Link to login without needing the password: <a href="<?php echo $secretUrl; ?>"><?php echo $secretUrl; ?></a><br>
<input type=button value="Reset secret link" onclick='location="?admin&resetSecret";'>
<input type=button value="What is this secret link for?" onclick='helpSecret(this);'><span id=helpSecretText></span><br>
<br>

<style>
	.onecharbtn {
		border: 1px solid black;
		color: black;
		background-color: #ddd;
		padding-left: 6px;
		padding-right: 6px;
		margin-left: 9px;
	}
	td {
		padding-bottom: 7px;
	}
	td:not(.noline) {
		border-top: 1px solid black;
		padding-top: 7px;
	}
	select {
		width: 90%;
	}
	p {
		max-width: 660px;
	}
</style>

<p>
The purpose of the category system is to be able to sort the list. Since all
stores have similar products near each other, you can scroll to the right
section of the list as you walk through the store, and have a quick overview of
what you need from this section. Example categories could be "Cheeses" and
"Fruits and vegetables".
</p>
<form method=POST action="?admin">
<table cellspacing=0><tr><th colspan=2>Categories</th></tr>
<?php 
$result = $db->query("SELECT id, name FROM categories WHERE uid = $_SESSION[uid] ORDER BY name") or die('Database error 810491');
while ($row = $result->fetch_row()) {
	$categories[$row[0]] = $row[1];
	$name = htmlentities($row[1], ENT_COMPAT | ENT_HTML401, 'UTF-8');
	echo "<tr><td>$name</td><td><input type=button class=onecharbtn onclick=\"rmcat($row[0]);\" value=x></td></tr>";
}
?>
<tr><td><input name=category></td><td><input type=submit value=Add></tr>
</table>
</form>

<br><br>
The number is how frequently it was used and decides the order in which it appears in the frequently used items.<br>
The right-hand buttons are for zeroing out the count, merging the item into another, and removing the item, respectively.<br>
<?php if (!isset($_GET['onlyuncat'])) { ?>
	(<a href='?admin&onlyuncat'>Show only Uncategorized</a>)
<?php } ?>
<table id=items cellspacing=0><tr><th colspan=2>Items</th></tr>
<?php 
$onlyuncat = '';
$urlonlyuncat = '';
if (isset($_GET['onlyuncat'])) {
	$onlyuncat = 'AND categoryid = -1';
	$urlonlyuncat = '&onlyuncat';
}

$result = $db->query("SELECT item, frequency, id, categoryid FROM popularitems WHERE uid = $_SESSION[uid] $onlyuncat ORDER BY frequency DESC, item") or die('Database error 142505');
if ($result->num_rows == 0) {
	echo '<tr><td colspan=2>(no items yet)</td></tr>';
}
while ($row = $result->fetch_row()) {
	$name = htmlentities($row[0], ENT_COMPAT | ENT_HTML401, 'UTF-8');
	echo "<tr><td>$name</td><td width=130>$row[1]"
		. "<input type=button class=onecharbtn onclick='resetCounter(\"$name\");' value=0>"
		. "<input type=button class=onecharbtn onclick=\"merge(&quot;$name&quot;);\" value=+>"
		. "<input type=button class=onecharbtn onclick='rm(\"$row[2]\");' value=x></td></tr>"
		. "<tr><td class=noline><select onchange='location=\"?admin&setCategory=\"+value+\"&itemid=$row[2]$urlonlyuncat#items\";'>";
	foreach ($categories as $id=>$category) {
		$selected = ($id == $row[3] ? ' selected' : '');
		echo "<option value=$id$selected>" . htmlentities($category, ENT_COMPAT | ENT_HTML401, 'UTF-8') . '</option>';
	}
	$selected = ($row[3] == -1 ? ' selected' : '');
	echo "<option value=-1$selected>Uncategorized</option>";
	echo "</select></td><td class=noline></td></tr>";
}
?>
</table>

