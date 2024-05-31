<?php 
	if ($_ok !== true) die('Error 49');

	if (isset($_GET['newStore'])) {
		$name = $db->escape_string($_GET['newStore']);
		$db->query("INSERT INTO stores (uid, name) VALUES($_SESSION[uid], '$name')") or die('Database error 598502');
		$id = $db->insert_id;
		header("Location: ?map&store=$id");
		exit;
	}

	if (isset($_POST['store'])) {
		$_GET['store'] = $_POST['store'];
	}

	if (!isset($_GET['store'])) {
		echo "<input type=button onclick=\"location='./';\" value=\"Back to grocery list\"><br><br>";
		$result = $db->query("SELECT id, name FROM stores WHERE uid = $_SESSION[uid] ORDER BY id DESC") or die('Database error 5182');
		echo 'Select a store:<br><select id=x onchange="selectedStore(this.value);">';
		while ($row = $result->fetch_row()) {
			echo "<option value=$row[0]>$row[1]</option>";
		}
		echo "<option value=-1>New store</option>";
		echo "</select><input type=button value='Go' onclick='selectedStore(false);'>";
		?>
			<script>
				function $(sel) { return document.querySelector(sel); }

				function selectedStore(id) {
					if (id === false) {
						id = $("#x").value;
					}
					if (id == -1) {
						location = '?map&newStore=' + escape(prompt("Store name?", ""));
					}
					else {
						location = '?map&store=' + id;
					}
				}
			</script>
		<?php 
		exit;
	}

	$store = intval($_GET['store']);
	$result = $db->query("SELECT id FROM stores WHERE uid = $_SESSION[uid] AND id = $store") or die('Database error 264972');
	if ($result->num_rows == 0) {
		die('Store does not exist or is not yours.');
	}

	$result = $db->query("SELECT id FROM stores WHERE uid = $_SESSION[uid] AND id = $store AND layout IS NOT NULL") or die('Database error 165972');
	if ($result->num_rows == 0) {
		// New store, upload layout!
		if (isset($_FILES['f'])) {
			if ($_FILES['f']['size'] > 1024 * 63) {
				die('Too large.');
			}

			$data = $db->escape_string(base64_encode(file_get_contents($_FILES['f']['tmp_name'])));
			$db->query("UPDATE stores SET layout = '$data', layoutdataformatversion=1 WHERE id = $store AND uid = $_SESSION[uid]") or die('Database error 81556');
		}
		else {
			?>
				<input type=button onclick="location='./';" value='Back to grocery list'> <input type=button onclick="location='?map&deleteStore=<?php echo $store; ?>';" value="Delete store"><br><br>
				<i>*sniff, sniff*</i> this store smells new! To map the store, you need to upload its general layout. You can even view a <a href="crappy-store-layout-example.png" target="_blank">crappy example</a>.
				Note that the size you upload, will be the size shown on page. It should be a PNG sketch.
				<form method=post enctype="multipart/form-data">
					<input type=file name=f>
					<input type=submit value=Upload>
				</form>
			<?php 
			$noimage = true;
		}
	}

	if (!isset($_GET['players'])) {
		echo "<input type=button onclick=\"location='./';\" value='Back to grocery list'><br><br>";
		if ( ! $noimage) {
			$result = $db->query("SELECT l.item FROM lists l "
				. "WHERE l.uid = $_SESSION[uid] AND l.item NOT IN (SELECT item FROM item_store_location isl WHERE isl.uid = $_SESSION[uid] AND isl.store = $store) "
				. "LIMIT 1") or die('Database error 552');
			if ($result->num_rows == 0) {
				$result = $db->query("SELECT pi.item FROM popularitems pi "
					. "WHERE pi.uid = $_SESSION[uid] AND pi.item NOT IN (SELECT item FROM item_store_location isl WHERE isl.uid = $_SESSION[uid] AND isl.store = $store) "
					. "LIMIT 1") or die('Database error 9159');
				if ($result->num_rows == 0) {
					?>
						<form action='./'>
							<input type=hidden name=map value=1>
							<input type=hidden name=store value=<?php echo $store;?>>
							Players: <input size=2 name=players type=numeric value=1><br>
							You are player number: <input size=2 name=player type=numeric value=1><br>
							<input type=radio name=onecart value=no checked> Each player has a cart<br>
							<input type=radio name=onecart value=yes> One cart for all players<br>
							<input type=submit value=map!>
						</form>
					<?php 
					$noimage = true;
				}
			}
		}
		if ( ! $noimage) {
			$result = $result->fetch_row();
			$result[0] = mb_convert_encoding($result[0], 'ISO-8859-1'); // TODO if we ever work on this again, please check why we don't use UTF-8 here instead
			echo "Where is '" . htmlspecialchars($result[0], ENT_COMPAT | ENT_HTML401 | ENT_QUOTES, 'ISO-8859-1') . "' in this store?<br>";
				?>
					<input type=button value='Not available in this store.' onclick='$("#loc").value = "n/a";'><br>
					<img src="?map&storelayout=<?php echo $store; ?>" id=map><br>
					<input id=loc value="Click on the map"><br>
					<input type=button value=Confirm onclick="go();">
					<script>
						function $(sel) { return document.querySelector(sel); }

						// Blatant rip from http://www.chestysoft.com/imagefile/javascript/get-coordinates.asp
						function FindPosition(oElement) {
							if(typeof( oElement.offsetParent ) != "undefined") {
								for(var posX = 0, posY = 0; oElement; oElement = oElement.offsetParent) {
									posX += oElement.offsetLeft;
									posY += oElement.offsetTop;
								}
								return [posX, posY];
							}
							else {
								return [oElement.x, oElement.y];
							}
						}

						function GetCoordinates(e)
						{
							var PosX = 0;
							var PosY = 0;
							var ImgPos;
							ImgPos = FindPosition($("#map"));
							if (!e) var e = window.event;
							if (e.pageX || e.pageY) {
								PosX = e.pageX;
								PosY = e.pageY;
							}
							else if (e.clientX || e.clientY) {
								PosX = e.clientX + document.body.scrollLeft + document.documentElement.scrollLeft;
								PosY = e.clientY + document.body.scrollTop + document.documentElement.scrollTop;
							}
							PosX = PosX - ImgPos[0];
							PosY = PosY - ImgPos[1];
							return [PosX, PosY];
						}

						function go() {
							location='?map&store=<?php echo $store;?>&item=<?php echo urlencode($result[0]); ?>&loc=' + escape($("#loc").value);
						}

						$("#map").onclick = function(ev) {
							var coords = GetCoordinates(ev);
							$("#loc").value = coords[0] + "," + coords[1];
						};
					</script>
				<?php
			}
			?>

			<hr>

			<script>
				function aGET(uri, callback) {
					var req = new XMLHttpRequest();
					req.open("GET", uri, true);
					req.send(null);
					req.onreadystatechange = function() {
						if (req.readyState == 4)
							callback(req.responseText);
					}
				}

				function checkSuccess(response) {
					if (response != '1') {
						alert(response);
					}
				}

				function togglePurchase(storeid, id, btn) {
					if (btn.value == '+') {
						aGET('?map&store=' + storeid + '&doPurchase=' + id, checkSuccess);
						btn.value = '-';
						btn.parentNode.querySelector('span').innerText = 'Yes';
					}
					else {
						aGET('?map&dontPurchase=' + id, checkSuccess);
						btn.value = '+';
						btn.parentNode.querySelector('span').innerText = 'No';
					}
				}
			</script>
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
					border-top: 1px solid black;
					padding-top: 7px;
				}
				p {
					max-width: 660px;
				}

				input[type="number"] {
					width: 50px;
				}
			</style>

			<strong>In what order do you walk past your categories in this store?</strong>
			<form method=post action='?map&catorder'>
				<input type=hidden name=store value=<?php echo $store; ?>>
				<?php
					$result = $db->query("SELECT c.id, c.name, (SELECT cs.priority FROM categories_stores cs WHERE cs.uid = $_SESSION[uid] AND cs.storeid = $store AND cs.categoryid = c.id) AS priority
						FROM categories c
						WHERE c.uid = $_SESSION[uid]
						ORDER BY priority, c.name") or die('Database error 488243');
					while ($row = $result->fetch_row()) {
						if ($row[2] === NULL) {
							$row[2] = 50;
						}
						echo "<input type=number name='catid$row[0]' value='$row[2]'> " . htmlescape($row[1]) . "<br>\n";
					}
				?>
				<input type=submit value=Save>
			</form>

			<br>
			<strong>Which items do you buy in this store?</strong>
			<table><tr><th>Item</th><th>Status</th></tr>
		<?php 

		// Get all the items we don't (yet) buy at this store
		$result = $db->query("SELECT pi.id, pi.item
			FROM popularitems pi
			WHERE pi.uid = $_SESSION[uid]
				AND pi.id NOT IN (
					SELECT i_s.itemid
					FROM item_stores i_s
					WHERE i_s.uid = $_SESSION[uid]
						AND i_s.storeid = $store
					)
			ORDER BY pi.item") or die('Database error 414929449');
		while ($row = $result->fetch_row()) {
			echo '<tr><td>' . htmlescape($row[1]) . '</td>'
				. "<td><span>No</span> <input type=button class=onecharbtn value=+ onclick='togglePurchase($store, $row[0], this);'></td></tr>";
		}

		// Get all the items we do buy at this store
		$result = $db->query("SELECT i_s.id, pi.item
			FROM item_stores i_s
			INNER JOIN popularitems pi ON pi.id = i_s.itemid
			WHERE i_s.uid = $_SESSION[uid]
				AND i_s.storeid = $store
			ORDER BY pi.item") or die('Database error 72747419');
		while ($row = $result->fetch_row()) {
			echo '<tr><td>' . htmlescape($row[1]) . '</td>'
				. "<td><span>Yes</span> <input type=button class=onecharbtn value=- onclick='togglePurchase($store, $row[0], this);'></td></tr>";
		}
		echo '</table>';

		exit;
	}

	$players = intval($_GET['players']);
	if ($players <= 0 || $players > 9) {
		$players = 1;
	}

	$player = intval($_GET['player']) - 1; // -1 because "Player #1" will be index 0.
	if ($player >= $players) {
		$player = $players - 1;
	}
	if ($player < 0) {
		$player = 0;
	}

	$playerRoutes = [];
	for ($i = 0; $i < $players; $i++) {
		$playerRoutes[$i] = [];
	}

	//$result = $db->query("SELECT MAX(locationx), MAX(locationy) FROM item_store_location WHERE uid = $_SESSION[uid] AND store = $store") or die('Database error 415');
	//list($maxx, $maxy) = $result->fetch_row();

	$result = $db->query("SELECT item, locationx, locationy FROM item_store_location "
		. "WHERE uid = $_SESSION[uid] AND store = $store AND item IN (SELECT item FROM lists WHERE uid = $_SESSION[uid]) "
		. "ORDER BY item") or die('Database error 5292589');
	$grocerylist = [];
	while ($row = $result->fetch_row()) {
		$grocerylist[$row[0]] = [$row[1], $row[2]];
	}

	$route = [];
	$location = [0, 0];
	while (count($grocerylist) > 0) {
		$closest = false;
		foreach ($grocerylist as $item => $itemlocation) {
			$distance = sqrt(pow(abs($location[0] - $itemlocation[0]), 2) + pow(abs($location[1] - $itemlocations[1]), 2));
			if ($closest === false || $distance < $closest) {
				$closest = $distance;
				$closestItem = $item;
			}
		}
		$route[] = $closestItem;
		unset($grocerylist[$closestItem]);
	}

	if ($_GET['onecart'] == 'yes') {
		while (count($route) > 0) {
			for ($i = 0; $i < $players; $i++) {
				$playerRoutes[$i][] = array_shift($route);
			}
		}
	}
	else {
		$chunk = ceil(count($route) / $players); // How many items each player gets
		for ($playern = 0; $playern < $players; $playern++) {
			for ($i = 0; $i < $chunk; $i++) {
				$playerRoutes[$playern][] = $route[$playern * $chunk + $i];
			}
		}
	}

?>

<input type=button onclick="location='./';" value="Back to grocery list"><br><br>
Your personal shopping list:
<?php 
	$r = 220;
	foreach ($playerRoutes[$player] as $item) {
		if ($r == 220) $r = 255; else $r = 220;
		echo "<div onclick='if(a==this)parentNode.removeChild(this);a=this;' style='margin-top: 20px; background-color:rgb($r, $r, $r);'>$item</div>\n";
	}
?>
<script src='res/common.js'></script>
<script>
	var els = $$("div");
	for (var i in els) {
		els[i].style.backgroundColor = CSSHSLHash(els[i].innerHTML, 100, 85);
	}

	a = 1;

	document.title = 'StoreSort - ' + document.title;
</script>
