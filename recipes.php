<?php 
	if ($_ok !== true) die('Error 49');

	$autofocus = false;

	function bbcode($input) {
		$input = htmlescape($input);
		return nl2br(
			str_replace("\n</ul>", '</ul>',
			str_replace("\n</ol>", '</ol>',
			str_replace("\n<ul>", '<ul>',
			str_replace("\n<ol>", '<ol>',
			str_replace("</ul>\n", '</ul>',
			str_replace("</ol>\n", '</ol>',
			str_replace("<ul>\n", '<ul>',
			str_replace("<ol>\n", '<ol>',
			str_replace("\r", '',
			str_replace('[b]', '<strong>',
			str_replace('[/b]', '</strong>',
			str_replace('[i]', '<i>',
			str_replace('[/i]', '</i>',
			str_replace('[u]', '<u>',
			str_replace('[/u]', '</u>',
			str_replace('[ul]', '<ul>',
			str_replace('[/ul]', '</ul>',
			str_replace('[ol]', '<ol>',
			str_replace('[/ol]', '</ol>',
			str_replace('[li]', '<li>',
			$input)))))))))))))))))))));
	}

	if ($_POST['action'] == 'addrecipe') {
		$db->query('INSERT INTO recipes (userid, name, instructions) VALUES(' . $_SESSION['uid'] . ', "' . $db->escape_string($_POST['name']) . '", "")');
		$_POST['recipeid'] = $db->insert_id;
		$_POST['action'] = 'Open recipe';
	}

	if (isset($_POST['recipeid'])) {
		$recipeid = intval($_POST['recipeid']);
		$result = $db->query("SELECT userid, name FROM recipes WHERE id = $recipeid") or die('Database error 32175');
		if ($result->num_rows == 0) {
			die('Cannot find recipe.');
		}
		list($recipeuid, $recipename) = $result->fetch_row();
		if ($recipeuid != $_SESSION['uid']) {
			die('That is not your recipe. You should not expect a present from santa this year.');
		}

		if ($_POST['action'] == 'Delete') {
			?>
				Are you sure you want to delete this recipe?
				<form method=post action='?recipes'>
					<input type=hidden name=action value=confirmDeletion>
					<input type=hidden name=recipeid value="<?php echo $recipeid; ?>">
					<input type=button value=No onclick='location="?recipes";'>
					<br><br><br>
					<input type=submit value=Yes>
				</form>
			<?php
			exit;
		}

		if ($_POST['action'] == 'confirmDeletion') {
			$db->query("DELETE FROM recipes WHERE id = $recipeid") or die('Database error 7426');
			$db->query("DELETE FROM `recipe-item` WHERE recipeid = $recipeid") or die('Database error 13577');
			echo '<strong>Deleted.</strong>';
		}

		if ($_POST['action'] == 'Add to list') {
			$result = $db->query("SELECT item, amount, unit FROM `recipe-item` WHERE recipeid = $recipeid") or die('Database error 19880');
			while ($row = $result->fetch_row()) {
				$item = $db->escape_string($row[0]);
				$r2 = $db->query("SELECT uid FROM lists WHERE item = '$item' AND uid = $_SESSION[uid]") or die('Database error 1292');
				if ($r2->num_rows > 0) {
					$itemname_html = htmlescape($row[0]);
					die("Cannot add recipe: item '$itemname_html' already on list. We need to implement amount adding!");
				}
			}
			// duplicate code, but this makes it easy to check everything before making any modifications...
			// Database transactions would be the clean way, but have had hard-to-reproduce deadlock issues on previous servers so I don't feel like re-testing that.
			$result = $db->query("SELECT item, amount, unit FROM `recipe-item` WHERE recipeid = $recipeid") or die('Database error 19880');
			while ($row = $result->fetch_row()) {
				$item = $db->escape_string($row[0]);
				$amount = intval($row[1]);
				$unit = $db->escape_string($row[2]);
				// TODO if grocery is already on the list, add the amounts instead
				$db->query("INSERT INTO lists (uid, item, amount, unit) VALUES($_SESSION[uid], '$item', $amount, '$unit')") or die('Database error 12901');
				$db->query("INSERT INTO changes (timestamp, changetype, item, uid, amount, unit) VALUES(" . time() . ", 'add', '$item', $_SESSION[uid], '$amount', '$unit')") or die('Database error 52209');
			}
			$changeId = $db->insert_id;
			$db->query("UPDATE users SET lastid = $changeId WHERE id = $_SESSION[uid]") or die('Database error 58489');
			echo '<strong>Added ' . htmlescape($recipename) . '.</strong>';
		}

		if ($_POST['action'] == 'updaterecipe') {
			$name = $db->escape_string($_POST['name']);
			$instructions = $db->escape_string($_POST['instructions']);
			$db->query("UPDATE recipes SET name = '$name', instructions = '$instructions' WHERE id = $recipeid") or die('Database error 12411');

			$db->query("DELETE FROM `recipe-item` WHERE recipeid = $recipeid") or die('Database error 1633');
			foreach ($_POST['item'] as $key=>$item) {
				if (empty($item)) continue;
				$amount = intval($_POST['amount'][$key]);
				$unit = $db->escape_string($_POST['unit'][$key]);
				$item = $db->escape_string($item);
				if ($amount == 0) $amount = 1;
				$db->query("INSERT INTO `recipe-item` (recipeid, item, amount, unit) VALUES($recipeid, '$item', $amount, '$unit')") or die('Database error 1633');
			}
			echo '<strong>Saved.</strong>';
			$autofocus = true;

			$_POST['action'] = 'Open recipe';
		}

		if ($_POST['action'] == 'Open recipe') {
			$checkmarkspan = '<span title="This item name has been previously used" onclick="alert(this.title);" style="cursor: pointer;">&checkmark;</span>';
			$badmarkspan = '<span title="This item name has not been seen before" onclick="alert(this.title);" style="cursor: pointer;">x</span>';
			$errormarkspan = '<span title="Unexpected server response while checking item name" onclick="alert(this.title);" style="cursor: pointer;">&#x2607;</span>';

			$result = $db->query("SELECT name, instructions FROM recipes WHERE id = $recipeid AND userid = $_SESSION[uid]") or die('Database error 15994');
			if ($result->num_rows != 1) {
				die('Either this recipe ID was not found, or this is not your recipe');
			}
			list($name, $instructions) = $result->fetch_row();
			?>
				<a href="./">Go to grocery list</a> |
				<a href="?recipes">Go to recipies</a><br><br>
				<form method=post accept-charset='utf-8'>
					<input type=hidden name=recipeid value=<?php echo $recipeid; ?>>
					<input type=hidden name=action value='updaterecipe'>
					Recipe: <input maxlength=255 name=name value="<?php echo htmlescape($name); ?>"><br>
					Instructions:<br>
					<?php
						echo '<span style="display: inline-block; background-color: #eee;">' . bbcode($instructions) . '</span>';

						echo '<br><br>Items (to delete one, leave the name empty):<br>';
						// note: $recipeid must be validated (to be our recipe) since `recipe-item` table has no uid field that we can check
						$result = $db->query("
							SELECT item, amount, unit, (SELECT COUNT(*) FROM popularitems pi WHERE ri.item = pi.item AND pi.uid = $_SESSION[uid])
							FROM `recipe-item` ri
							WHERE recipeid = $recipeid") or die('Database error 22363: ' . $db->error);
						while ($row = $result->fetch_row()) {
							echo '<input name="amount[]" type=number placeholder=500 style="width: 70px;" value=' . $row[1] . '> '
								. '<input name="unit[]" size=4 value="' . htmlescape($row[2]) . '"> '
								. '<input name="item[]" value="' . htmlescape($row[0]) . '" class=itemName>'
								. '<span class=verifiedMarker>' . ($row[3] > 0 ? $checkmarkspan : '') . '</span><br>';
						}
						echo '<input name="amount[]" type=number placeholder=500 style="width: 70px;"> '
							. '<input name="unit[]" size=4 placeholder=grams> <input placeholder=flour ' . ($autofocus ? 'autofocus' : '') . ' name="item[]" class=itemName>'
							. '<span class=verifiedMarker></span>';
					?>
					<br><br>
					Edit instructions:<br>
					(You can use [b], [i], [u], [ul], [ol], [li].)<br>
					<textarea name=instructions cols=80 rows=20><?php echo htmlescape($instructions); ?></textarea><br>
					<input type=submit value=Save>
				</form>
				<script>
					let newItemElement = document.querySelector('#newItem');
					let newItemVerifiedElement = document.querySelector('#newItemVerified');
					document.querySelectorAll('input.itemName').forEach((el) => {
						el.onkeyup = function(ev) {
							let sibling = ev.target.nextSibling;
							if ( ! sibling.classList.contains('verifiedMarker')) {
								return;
							}
							sibling.innerHTML = '&#x231B;';
							fetch(`?checkItem=${encodeURIComponent(ev.target.value)}`).then((useless) => {
								return useless.text();
							}).then((result) => {
								// check if the element still contains this text now that the network request returned. Otherwise we're showing a result for a different string...
								if (result == ev.target.value + '1') {
									sibling.innerHTML = '<?php echo $checkmarkspan; ?>';
								}
								else if (result == ev.target.value + '0') {
									sibling.innerHTML = '<?php echo $badmarkspan; ?>';
								}
								else if (sibling.innerHTML == '&#x231B;') {  // only show an error if there is a loading symbol currently, otherwise a valid response may already have returned
									sibling.innerHTML = '<?php echo $errormarkspan; ?>';
								}
							});
						};
					});
				</script>
			<?php
			exit;
		}
	}
?> <a href="./">Go to grocery list</a><br><br>
<form method=post accept-charset='utf-8'>
	<input type=hidden name=action value=addrecipe>
	<strong>New recipe</strong><br>
	Name: <input name=name><br>
	<input type=submit value='Add items'>
</form>
<br>
<script>
	document.title = 'Recipes - ' + document.title
</script>
<?php
	$result = $db->query("SELECT id, name FROM recipes WHERE userid = $_SESSION[uid]") or die('Database error 22998');
	echo "Showing $result->num_rows existing recipes.<table border=1 cellpadding=5>";
	echo '<tr><th>Name</th><th></th><th></th><th></th></tr>';
	while ($row = $result->fetch_row()) {
		echo "<form method=post accept-charset='utf-8'><input type=hidden name=recipeid value=$row[0]>";
		echo '<tr><td>' . htmlescape($row[1]) . '</td>'
			. '<td><input type=submit name=action value="Open recipe"></td>'
			. '<td><input type=submit name=action value="Add to list"></td>'
			. '<td><input type=submit name=action value="Delete"></td></tr></form>';
	}

