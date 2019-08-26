<?php 
	if ($_ok !== true) die('Error 49');

	$autofocus = false;

	function bbcode($input) {
		$input = htmlentities($input, ENT_COMPAT | ENT_HTML401, 'UTF-8');
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
		$_POST['action'] = 'View/edit';
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

		if ($_POST['action'] == 'Add to groceries') {
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
			echo '<strong>Added ' . htmlentities($recipename) . '.</strong>';
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

			$_POST['action'] = 'View/edit';
		}

		if ($_POST['action'] == 'View/edit') {
			$result = $db->query("SELECT name, instructions FROM recipes WHERE id = $recipeid") or die('Database error 15994');
			list($name, $instructions) = $result->fetch_row();
			?>
				<a href="./">Go to grocery list</a> |
				<a href="?recipes">Go to recipies</a><br><br>
				<form method=post accept-charset='utf-8'>
					<input type=hidden name=recipeid value=<?php echo $recipeid; ?>>
					<input type=hidden name=action value='updaterecipe'>
					Recipe: <input maxlength=255 name=name value="<?php echo htmlentities($name, ENT_COMPAT | ENT_HTML401, 'UTF-8'); ?>"><br>
					Instructions:<br>
					<?php
						echo '<span style="display: inline-block; background-color: #eee;">' . bbcode($instructions) . '</span>';

						echo '<br><br>Items (to delete one, leave the name empty):<br>';
						$result = $db->query("SELECT item, amount, unit FROM `recipe-item` WHERE recipeid = $recipeid") or die('Database error 22363');
						while ($row = $result->fetch_row()) {
							echo '<input name="amount[]" type=number placeholder=500 style="width: 70px;" value=' . $row[1] . '> '
								. '<input name="unit[]" size=4 value="' . htmlentities($row[2], ENT_COMPAT | ENT_HTML401, 'UTF-8') . '"> '
								. '<input name="item[]" value="' . htmlentities($row[0], ENT_COMPAT | ENT_HTML401, 'UTF-8') . '"><br>';
						}
						echo '<input name="amount[]" type=number placeholder=500 style="width: 70px;"> '
							. '<input name="unit[]" size=4 placeholder=grams> <input placeholder=flour ' . ($autofocus ? 'autofocus' : '') . ' name="item[]">';
					?>
					<br><br>
					Edit instructions:<br>
					(You can use [b], [i], [u], [ul], [ol], [li].)<br>
					<textarea name=instructions cols=80 rows=20><?php echo htmlentities($instructions, ENT_COMPAT | ENT_HTML401, 'UTF-8'); ?></textarea><br>
					<input type=submit value=Save>
				</form>
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
<?php
	$result = $db->query("SELECT id, name FROM recipes WHERE userid = $_SESSION[uid]") or die('Database error 22998');
	echo "Showing $result->num_rows existing recipes.<table border=1>";
	echo '<tr><th>Name</th><th></th><th></th><th></th></tr>';
	while ($row = $result->fetch_row()) {
		echo "<form method=post accept-charset='utf-8'><input type=hidden name=recipeid value=$row[0]>";
		echo '<tr><td>' . htmlentities($row[1], ENT_COMPAT | ENT_HTML401, 'UTF-8') . '</td>'
			. '<td><input type=submit name=action value="Add to groceries"></td>'
			. '<td><input type=submit name=action value="View/edit"></td>'
			. '<td><input type=submit name=action value="Delete"></td></tr></form>';
	}

