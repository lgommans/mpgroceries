<?php 
if ($_ok !== true) die('Error 49');

if ($_SESSION['loggedin'] === 'yes') {
	if (mt_rand(0, 100) == 1) {
		$db->query('DELETE FROM changes WHERE timestamp < ' . (time() - 3600 * 24 * 30)) or die('Database failure 4891');
	}

	if (isset($_GET['checkItem'])) {
		// start by printing the item name that this result is valid for
		header('Content-Type: text/plain');
		print($_GET['checkItem']);

		$result = $db->query("SELECT item FROM popularitems WHERE uid = $_SESSION[uid] AND item = '" . $db->escape_string($_GET['checkItem']) . "'") or die('Database error 139');
		if ($result->num_rows > 0) {
			die('1');
		}
		else {
			die('0');
		}
	}

	if (isset($_GET['map'])) {
		if (isset($_GET['deleteStore'])) {
			$store = intval($_GET['deleteStore']);
			$db->query("DELETE FROM stores WHERE id = $store AND uid = $_SESSION[uid]") or die('Database error 7359452');

			header('Location: ?map');
			exit;
		}

		if (isset($_GET['catorder'])) {
			$store = intval($_POST['store']);

			foreach ($_POST as $key=>$val) {
				if (substr($key, 0, 5) == 'catid') {
					$catid = intval(substr($key, 5));
					$prio = floatval($val);
					$db->query("INSERT INTO categories_stores (priority, storeid, categoryid, uid) VALUES($prio, $store, $catid, $_SESSION[uid])
						ON DUPLICATE KEY UPDATE priority = $prio") or die('Database error 737594');
				}
			}
			header('Location: ?map&store=' . $store);
			exit;
		}

		if (isset($_GET['rename'])) {
			$store = intval($_POST['store']);

			$newname_escaped = $db->escape_string($_POST['newname']);
			$db->query("UPDATE stores SET name = '$newname_escaped' WHERE id = $store AND uid = $_SESSION[uid]") or die('Database error 9584948');

			header('Location: ?map&store=' . $store);
			exit;
		}

		if (isset($_GET['dontPurchase'])) {
			$itemstoreid = intval($_GET['dontPurchase']);
			$db->query("DELETE FROM item_stores WHERE uid = $_SESSION[uid] AND id = $itemstoreid") or die('Database error 6446');
			die('1');
		}

		if (isset($_GET['doPurchase']) && isset($_GET['store'])) {
			$storeid = intval($_GET['store']);
			$result = $db->query("SELECT id FROM stores WHERE uid = $_SESSION[uid] AND id = $storeid") or die('Database error 4818413');
			if ($result->num_rows != 1) {
				die('That is not your store. This incident will be reported.');
			}

			$itemid = intval($_GET['doPurchase']);
			$db->query("INSERT INTO item_stores (uid, itemid, storeid) VALUES ($_SESSION[uid], $itemid, $storeid)") or die('Database error 81941');
			die('1');
		}

		if (isset($_GET['loc'])) {
			$store = intval($_GET['store']);
			if ($_GET['loc'] == 'n/a') {
				$locx = 'NULL';
				$locy = 'NULL';
			}
			else {
				$loc = explode(',', $_GET['loc']);
				$locx = intval($loc[0]);
				$locy = intval($loc[1]);
			}
			$item = mb_convert_encoding($db->escape_string($_GET['item']), 'UTF-8', 'ISO-8859-1');
			$db->query("INSERT INTO item_store_location (uid, item, store, locationx, locationy) VALUES($_SESSION[uid], '$item', $store, $locx, $locy)") or die('Database error 4525');
			header("Location: ?map&store=$store");
		}

		if (isset($_GET['storelayout'])) {
			$id = intval($_GET['storelayout']);
			$result = $db->query("SELECT layout, layoutdataformatversion FROM stores WHERE id = $id AND uid = $_SESSION[uid]") or die('Database error 585928');
			$result = $result->fetch_row();
			if ($result[1] == 0) { // raw
				$layout = $result[0];
			}
			else if ($result[1] == 1) { // base64-encoded
				$layout = base64_decode($result[0]);
			}
			else {
				die('Unknown store data format.');
			}
			header('Content-Length: ' . strlen($layout));
			header('Content-Type: image/png');
			die($layout);
		}
	}

	header('Content-Type: text/html; charset=ascii');
	$type = '';
	$item = '';
	switch (true) {
		case isset($_GET['update']):
			$type = 'update';

			// fall through
		case isset($_GET['add']):
			if ($type == '') {
				$type = 'add';
			}

			// fall through
		case isset($_GET['remove']):
			if ($type == '') {
				$type = 'remove';
			}

			$item = $db->escape_string($_GET['item']);
			list($amount, $unit) = splitAmount($db->escape_string($_GET['amount']));
			if (empty($amount) && empty($unit)) {
				$amount = '1';
				$unit = '';
			}

			if ($type == 'add') {
				$db->query("INSERT INTO lists (uid, item, amount, unit) VALUES($_SESSION[uid], '$item', '$amount', '$unit')") or die('Database error 838');
				$db->query("INSERT INTO popularitems (uid, item, frequency) VALUES($_SESSION[uid], '$item', 1) "
					. "ON DUPLICATE KEY UPDATE frequency = frequency + 1") or die('Database error 5829693');
			}
			else if ($type == 'update') {
				$db->query("UPDATE lists SET amount = '$amount', unit = '$unit' WHERE uid = $_SESSION[uid] AND item = '" . $item . "'") or die('Database error 1583994');
			}
			else if ($type == 'remove') {
				$db->query("DELETE FROM lists WHERE uid = $_SESSION[uid] AND item = '$item'") or die('Database error 838');
			}

			// fall through
		case isset($_GET['clearList']):
			if ($type == '') {
				$type = 'clear';
				$db->query("DELETE FROM lists WHERE uid = $_SESSION[uid]") or die('Database error 849539');
			}

			$db->query("INSERT INTO changes (timestamp, changetype, item, uid, amount, unit) VALUES(" . time() . ", '$type', '$item', $_SESSION[uid], '$amount', '$unit')") or die('Database error 58205');
			$changeId = $db->insert_id;
			$db->query("UPDATE users SET lastid = $changeId WHERE id = $_SESSION[uid]") or die('Database error 589');

			die('ok');

		case isset($_GET['getPopularItems']):
			$result = $db->query("SELECT item, categoryid FROM popularitems "
				. "WHERE uid = $_SESSION[uid] AND item NOT IN (SELECT item FROM lists WHERE uid = $_SESSION[uid]) ORDER BY frequency DESC") or die('Database error 142449');
			die(json_encode($result->fetch_all(MYSQLI_NUM)));

		case isset($_GET['getListUpdatesSince']):
			$lastid = intval($_GET['getListUpdatesSince']);
			$result = $db->query("SELECT changetype, item, id, amount, unit FROM changes WHERE id > $lastid AND uid = $_SESSION[uid] ORDER BY id") or die('Database error 1948');
			die(json_encode($result->fetch_all(MYSQLI_NUM)));
	}
}

