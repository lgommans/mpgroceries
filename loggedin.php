<?php 
	if ($_ok !== true) die('Error 49');

	if (isset($_GET['secret'])) {
		// We're logged in, but a secret login parameter is present. Let's relogin using this parameter.
		unset($_SESSION);
		require('login.php');
	}
?>
<noscript>
	<b>Error: javascript disabled or unavailable. This is supposed to be multiplayer, so you are supposed to have Javascript enabled and use it interactively.</b>
</noscript>

<div id=status>&nbsp;</div>

<input type=button value='Clear' onclick='btnClear(this);'>
<input type=button value='Sort' onclick='location="?map";'>
<input type=button value='Recipes' onclick='location="?recipes";'>
<input type=button value='+' id=morebtnsbtn onclick='moreButtons();'>
<div id=morebtns style='display: none;'>
	<?php
		if (!isset($_GET['categorized'])) {
			echo "<input type=button value='Categorize' onclick='location=\"?categorized\";'>";
		}
		else {
			echo "<input type=button value='Uncategorize' onclick='location=\"./\";'>";
		}
	?>
	<input type=button value='Manage' onclick='location="?admin";'>
	<input type=button value='Sync' onclick='refresh();'>
	<input type=button value='Hard reload' onclick='location.reload();'>
	<input type=button value='Combos' onclick='location="?combinations";'>
	<input type=button value='Logout' onclick='btnLogout();'>
	<br>
	<?php
		if ( ! isset($_GET['store'])) {
			$result = $db->query("SELECT id, name FROM stores WHERE uid = $_SESSION[uid] ORDER BY id DESC") or die('Database error 1939195');
			if ($result->num_rows > 0) {
				echo '<select id="selectStore"><option value="-1">Filter groceries by store</option>';
				while ($row = $result->fetch_row()) {
					echo "<option value=$row[0]>" . htmlentities($row[1], ENT_COMPAT | ENT_HTML401, 'UTF-8') . '</option>';
				}
				echo '</select>';
			}
		}
	?>
</div>
<?php
	if (isset($_GET['store'])) {
		echo '<input type=button value="Cancel store filtering" onclick="location=\'./\';">';
	}

	if (isset($_GET['firstuse'])) {
		?>
			<br><strong>First time usage info:</strong><br>
			- the "Amount" fields are optional<br>
			- tap an item to remove it or edit its amount<br>
			<a href="./">Dismiss</a><br>
		<?php
	}
?>

<div id=addItems>
	Item: <input maxlength=75 id=addItemsInput style="margin-top: 15px;" placeholder=Flour><br>
	Amount: <input type=number style='width: 65px' maxlength=25 id=addAmount placeholder=250>
	<input type=text size=2 id=addUnit maxlength=20 placeholder=g>
	<input type=button value=Add onclick="addItem(false);" style="margin-bottom: 15px;"><br>
	<div id=popularItems style="max-width: 720px; max-height: 140px; overflow-y: scroll"></div>
</div>

<div id=listDisplay style="max-width: 720px; margin-bottom: 1000px;">
</div>

<script>
	confirmationButtonString = 'Are you sure?';

	function $(sel) { return document.querySelector(sel); }
	function $$(sel) { return document.querySelectorAll(sel); }

	function newDiv() {
		return document.createElement("div");
	}

	function aGET(uri, callback, noStatus) {
		if (!noStatus) noStatus = false;

		var reqnum = requests++;
		if (!noStatus) {
			$('#status').innerHTML += reqnum + ', ';
			//console.log("Req " + reqnum + "=" + uri);
		}
		var req = new XMLHttpRequest();
		req.open("GET", uri, true); req.send(null);
		req.onreadystatechange = function() {
			if (req.readyState == 4) {
				if (!noStatus) {
					$('#status').innerHTML = $('#status').innerHTML.replace(reqnum + ', ', '');
				}
				if (callback) {
					callback(req.responseText);
				}
			}
			if (req.readyState == 5) {
				alert('Network error.');
			}
		};
	}

	function moreButtons() {
		if ($("#morebtns").style.display == 'none') {
			$("#morebtns").style.display = 'inline';
			$("#morebtnsbtn").value = '-';
		}
		else {
			$("#morebtns").style.display = 'none';
			$("#morebtnsbtn").style.display = 'inline';
			$("#morebtnsbtn").value = '+';
		}
	}

	function btnClear(btn) {
		if (btn.value == confirmationButtonString) {
			queue('?clearList');
			updatePopularItems();
			list = [];
			$('#listDisplay').innerHTML = '';
			btn.value = 'Clear';
			clearTimeout(btnClearTimeout);
		}
		else {
			btn.value = confirmationButtonString;
			btnClearTimeout = setTimeout(function() {
				btn.value = 'Clear';
			}, 5000);
		}
	}

	function btnLogout() {
		location = '?logout&csrf=<?php echo $_SESSION['csrf']; ?>';
	}

	function refresh() {
		clearTimeout(listUpdateTimer);
		getListUpdates();
		processReqQueue();
	}

	function addItem(item, sendUpdate, amount, unit) {
		if (list.indexOf(item) != -1) {
			return;
		}

		if (item === false) {
			item = $('#addItemsInput').value;
			amount = $('#addAmount').value;
			unit = $('#addUnit').value;
			sendUpdate = true;
		}

		if (item == '') {
			return;
		}

		if (!amount || amount.trim() == '') {
			amount = '1';
			if (!unit) {
				unit = '';
			}
		}

		if ((item === false || sendUpdate === true) && $("#addItemsInput").value != '') {
			$('#addItemsInput').value = '';
			$('#addAmount').value = '';
			$('#addUnit').value = '';
			$("#addItemsInput").focus();
			updatePopularItemsDisplay();
		}

		if (sendUpdate) {
			queue('?add&item=' + encodeURIComponent(item) + '&amount=' + encodeURIComponent(amount + ' ' + unit));
			updatePopularItems();
		}

		list.push(item);
		var itemDiv = newDiv();
		itemDiv.innerHTML = formatAmount(item, amount, unit);
		itemDiv.className = 'item';
		itemDiv.style.backgroundColor = CSSHSLHash(item, 100, 85);
		itemDiv.style.marginTop = '20px';
		itemDiv.onclick = function() {
			confirmRemove(item);
		}
		$("#listDisplay").appendChild(itemDiv);
	}

	function escapeHtml(str) {
		/*if ( ! str.replace) {
			return str;
		}*/
		return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace("'", '&#39;').replace('"', '&quot;');
	}

	function confirmRemove(item) {
		if (window.selectedItem !== false && window.selectedItem != item) {
			// Avoid bug where you can select another item before the old one expired
			return;
		}
		if (window.selectedItem == item) {
			queue('?remove&item=' + encodeURIComponent(item));
			updatePopularItems();
			removeItem(item);
			//clearTimeout(tapTimeout);
			tappedItem.parentNode.removeChild(tappedItem);
			selectedItem = false;
			selectedOperationsDiv.parentNode.removeChild(selectedOperationsDiv);
		}
		else {
			selectedItem = item;
			$$('.item').forEach(function(el, i) {
				if (el.innerHTML && stripAmount(el.innerHTML) == item) {
					tappedItem = el;
					tmpContents = el.innerHTML;
					var bgColor = el.style.backgroundColor;
					tapTimeoutFunction = function() {
						tappedItem.innerHTML = tmpContents;
						tappedItem.style.backgroundColor = bgColor;
						selectedItem = false;
						selectedOperationsDiv.parentNode.removeChild(selectedOperationsDiv);
					};
					//tapTimeout = setTimeout(tapTimeoutFunction, 4000);

					el.innerText = 'Tap again to delete';
					el.style.backgroundColor = '#f44';

					selectedOperationsDiv = newDiv();
					var amount = tmpContents.substring(6, tmpContents.indexOf('</span>'));
					selectedOperationsDiv.innerHTML = '<br>Amount: <input id=amount value="' + escapeHtml(amount) + '" size=3>'
						+ '<input type=button value=v onclick="updateAmount(' + i + ', \'' + escapeHtml(item) + '\');">';
					el.parentNode.insertBefore(selectedOperationsDiv, el.nextSibling);
				}
			});
		}
	}

	function findItemElement(item) {
		var found;
		$$('.item').forEach(function(el) {
			if (el.innerHTML && stripAmount(el.innerHTML) == item) {
				found = el;
			}
		});
		return found;
	}

	function updateAmount(elindex, item) {
		var items = $$('.item');
		var amount = $('#amount').value;

		//clearTimeout(tapTimeout);
		tapTimeoutFunction();

		items[elindex].innerHTML = '<span>' + escapeHtml(amount) + '</span>' + escapeHtml(item);
		// TODO would be nice to dedup the formatting line above with formatAmount()
		queue('?update&item=' + encodeURIComponent(item) + '&amount=' + encodeURIComponent(amount));
	}

	function stripAmount(html) {
		if (html.indexOf('<span>') == -1) return html;
		return html.substring(html.indexOf('</span>')+7);
	}

	function formatAmount(item, amount, unit) {
		return '<span>' + escapeHtml(amount) + escapeHtml(unit) + '</span>' + escapeHtml(item);
	}

	function updateItem(item, amount, unit) {
		// Never send an update: the event handler of the button already does that. If this function is called, it was incoming.
		findItemElement(item).innerHTML = formatAmount(item, amount, unit);
	}

	function removeItem(item) {
		// Never send an update: the event handler for removing items already does that. If this function is called, it was incoming.
		list = []; // You can't remove indices from an array, so we need to rebuild the array
		$$('.item').forEach(function(el) {
			if (stripAmount(el.innerHTML) == item) {
				//console.log('Removing ' + item);
				$('#listDisplay').removeChild(el);
				// Don't break after finding the right div because we're still rebuilding the list array.
			}
			else {
				list.push(stripAmount(el.innerHTML));
			}
		});
	}

	function getListUpdates() {
		aGET('?getListUpdatesSince=' + lastUpdateId, function(data) {
			data = JSON.parse(data);
			var through = false;
			for (var i in data) {
				if (data[i][0] == 'add') {
					addItem(data[i][1], false, data[i][3], data[i][4]);
				}
				if (data[i][0] == 'remove') {
					removeItem(data[i][1]);
				}
				if (data[i][0] == 'update') {
					updateItem(data[i][1], data[i][3], data[i][4]);
				}

				// This is in the middle of data[i][0] checks, because in
				// case of a 'clear' it gets overwritten locally.
				lastUpdateId = data[i][2];

				if (data[i][0] == 'clear') {
					$('#listDisplay').innerHTML = '';
					updatePopularItems();
					list = [];
				}
			}
		}, true);
		window.listUpdateTimer = setTimeout(getListUpdates, 22 * 1000 + Math.random() * 6000);
	}

	function modulo(divident, divisor) {
		// Via http://stackoverflow.com/a/2772402/1201863
		// cc by-sa 3.0 with attribution required
		divident = divident.toString();
		divisor = divisor.toString();
		var cDivident = '';
		var cRest = '';

		for (var i in divident) {
			var cChar = divident[i];
			var cOperator = cRest + '' + cDivident + '' + cChar;

			if (cOperator < parseInt(divisor)) {
				cDivident += '' + cChar;
			}
			else {
				cRest = cOperator % divisor;
				if (cRest == 0) {
					cRest = '';
				}
				cDivident = '';
			}

		}
		cRest += '' + cDivident;
		if (cRest == '') {
			cRest = 0;
		}
		return cRest;
	}

	function bkdrHash(str) {
		var seed = 131;
		var hash = 0;
		for (var i in str) {
			// Original implementations used uint, so let's make sure our output falls in the same range and do a modulo.
			// Unfortunately, Javascript is not python and silently fails at modulo on large numbers.
			// The custom modulo() function is also not perfect, still failing at very large numbers, but it *probably* works for this use case. More testing is needed to be certain.
			hash = modulo((hash * seed) + str.charCodeAt(i), Math.pow(2, 32));
		}
		return hash;
	}

	function CSSHSLHash(str, saturation, lightness) {
		return 'hsl(' + modulo(bkdrHash(str.toLowerCase()), 360) + ', ' + saturation + '%, ' + lightness + '%)';
	}

	function updatePopularItems() {
		aGET('?getPopularItems&r=' + Math.random(), function(data) {
			var tmp = JSON.parse(data);
			popularItems = [];
			for (var i in tmp) {
				popularItems.push(tmp[i][0]);

				/*  If we ever decide to do this locally, this could come in handy...
				var item = tmp[i][0];
				var categoryId = tmp[i][1];
				if ( ! categoryId in categories) {
					console.log("Warning 949491");
					continue;
				}
				var categoryName = categories[categoryId];

				popularItems.push(item);
				itemCategories[item] = categoryName;
				if ( ! (categoryName in categoryItems)) {
					categoryItems[categoryName] = [];
				}
				categoryItems[categoryName].push(item);
				*/
			}
			updatePopularItemsDisplay();
		});
	}

	function updatePopularItemsDisplay() {
		$("#popularItems").innerHTML = '';
		var searchKey = $("#addItemsInput").value;
		var html = '';
		var displayedItemCount = 0;
		for (var i in popularItems) {
			if (searchKey == '' || popularItems[i].toLowerCase().indexOf(searchKey.toLowerCase()) != -1) {
				var style = 'style="background:' + CSSHSLHash(popularItems[i], 100, 85) + '; margin: 5px;"';
				html += '<input type=button value="' + escapeHtml(popularItems[i]) + '" ' + style + ' onclick=\'addItem("' + escapeHtml(popularItems[i]) + '", true);\'> ';
				displayedItemCount++;
				if (displayedItemCount > 26) {
					break; // Limit the amount of items.
				}
			}
		}
		if (popularItems.length == 0) {
			html = 'As you add/remove items to/from your grocery list, the items you use most often will appear here.';
		}
		$('#popularItems').innerHTML = html;
	};

	function queue(uri, callback, nostatus) {
		req_queue.push([uri, callback, nostatus]);
		processReqQueue();
	}

	function processReqQueue() {
		for (var i in req_queue) {
			var uri = req_queue[i][0];
			var callback = req_queue[i][1];
			var nostatus = req_queue[i][2]; // whether it should display in the requests list

			var currentlyBeingProcessed = $("#status").innerText.indexOf(i.toString() + ', ') != -1;
			if (currentlyBeingProcessed) {
				// Do not add duplicates...
				continue;
			}

			aGET(uri, function(data) {
				if (data == 'ok') {
					//console.log("Request " + uri + " succeeded. Removing index " + i + " from req queue");
					if (callback) {
						callback(data);
					}
					req_queue.splice(i, 1);
				}
				else {
					//console.log("Got a 200 but no ok.");
				}
			}, nostatus);
		}
	}

	if ($("#selectStore")) {
		$("#selectStore").onchange = function() {
			var val = $("#selectStore").value;
			if (val == -1) return;
			location = '?store=' + val;
		};
	}

	$("#addUnit").onkeyup = $("#addAmount").onkeyup = function(ev) {
		if (ev.keyCode == 13) {
			addItem(false);
		}
	};

	$("#addItemsInput").onkeyup = function(ev) {
		if (ev.keyCode == 13) {
			addItem(false);
		}
		updatePopularItemsDisplay();
	};

	req_queue = [];
	requests = 0;
	lastUpdateId = -1;
	list = []; // The current local state of the grocery list
	popularItems = [];
	selectedItem = false;
	itemCategories = {};
	categoryItems = {};

	data = [<?php 
		$storefilter = '';
		if (isset($_GET['store'])) {
			$store = intval($_GET['store']);
			$result = $db->query("SELECT id FROM stores WHERE id = $store AND uid = $_SESSION[uid]") or die('Database error 8148304');
			if ($result->num_rows == 1) {
				$storefilter = "AND pi.id IN (SELECT itemid FROM item_stores WHERE uid = $_SESSION[uid] AND storeid = $store)";
			}
		}
		$result = $db->query("SELECT lastid FROM users WHERE id = $_SESSION[uid]") or die('Database error 234920');
		$lastid = $result->fetch_row()[0];
		$result = $db->query("SELECT l.item, l.amount, l.unit
			FROM lists l
			INNER JOIN popularitems pi ON l.item = pi.item AND pi.uid = $_SESSION[uid]
			WHERE l.uid = $_SESSION[uid]
			$storefilter
			ORDER BY " . (isset($_GET['categorized']) ? 'pi.categoryid, ' : '') . "item
		") or die('Database error 3209');
		$itemsJson = json_encode($result->fetch_all(MYSQLI_NUM));
		echo("$lastid, $itemsJson");
	 ?>];
	lastUpdateId = data[0];

	for (var i in data[1]) {
		addItem(data[1][i][0], false, data[1][i][1], data[1][i][2]);
	}

	/* If we ever want to do categorization locally, this could come in handy
	categories = {'0': 'Uncategorized'
		<?php
			//$result = $db->query("SELECT id, name FROM categories WHERE uid = $_SESSION[uid]") or die('Database error 2423940');
			//while ($row = $result->fetch_row()) {
				//$row[1] = htmlentities($row[1], ENT_COMPAT | ENT_HTML401, 'UTF-8');
				//echo ",'$row[0]': '$row[1]'";
			//}
		?>
	};*/

	updatePopularItems();
	setTimeout(getListUpdates, 350);
</script>
<style>
	#listDisplay span {
		color: #555;
		display: inline-block;
		margin-right: 6px;
	}
</style>
