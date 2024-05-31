<?php 
	if ($_ok !== true) die('Error 49');

	?>
		<a href="./">Return to grocery list</a>
		<script>
			document.title = 'Combinations - ' + document.title;
		</script>
		<br><br>(todo: make this page load 10 items ahead and ajax the answers...)<br><br>
	<?php

	// database info:
	// popularitems.edible = -1 unknown, 0 inedible, 1 edible
	// combinations.answer = 0 absolutely not, 1 maybe/skip for now, 2 yes

	if (isset($_POST['itemid'])) {
		$itemid = intval($_POST['itemid']);
		$edible = (($_POST['edible'] == 'Yes') ? 1 : 0);
		$db->query("UPDATE popularitems SET edible = $edible WHERE uid = $_SESSION[uid] AND id = $itemid") or die('Database error 158294');
	}

	if (isset($_POST['pi1id'])) {
		$pi1id = intval($_POST['pi1id']);
		$pi2id = intval($_POST['pi2id']);
		if ($_POST['answer'] == 'Yes') {
			$answer = 2;
		}
		else if ($_POST['answer'] == 'Absolutely not') {
			$answer = 0;
		}
		else {
			$answer = 1;
		}
		$db->query("INSERT INTO combinations (uid, pi1, pi2, answer) VALUES($_SESSION[uid], $pi1id, $pi2id, $answer)") or die('Database error 13510');
	}

	$unknownItems = $db->query("SELECT id, item FROM popularitems WHERE edible < 0 AND uid = $_SESSION[uid] ORDER BY frequency DESC") or die('Database error 7295492');
	if ($unknownItems->num_rows > 0) {
		list($itemid, $name) = $unknownItems->fetch_row();
		print("<form method=post action='?combinations'>");
		print("<input type=hidden name=itemid value=$itemid>");
		print("Is/are <strong>" . htmlescape($name) . "</strong> edible?<br>Or rather, can you combine it with <i>anything</i>?<br>");
		print("<input type=submit name=edible value=Yes>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;");
		print("<input type=submit name=edible value=No>");
		print("</form>");
	}

	print('<br><br><br>');

	$unmatchedItems = $db->query("
		SELECT pi1.id, pi1.item, pi2.id, pi2.item
		FROM popularitems pi1
		JOIN popularitems pi2
		WHERE (
				SELECT COUNT(*)
				FROM combinations c
				WHERE
					(c.pi1 = pi1.id AND c.pi2 = pi2.id)
					OR
					(c.pi1 = pi2.id AND c.pi2 = pi1.id)
			) = 0
			AND pi1.uid = $_SESSION[uid]
			AND pi2.uid = $_SESSION[uid]
			AND pi1.edible != 0 
			AND pi2.edible != 0
			AND pi1.id != pi2.id
		ORDER BY RAND() * pi1.frequency + RAND() * pi2.frequency DESC
	") or die('Database error 813519: '.$db->error);

	if ($unmatchedItems->num_rows == 0) {
		$unmatchedItems = $db->query("
			SELECT pi1.id, pi1.item, pi2.id, pi2.item
			FROM popularitems pi1
			JOIN popularitems pi2
			INNER JOIN combinations c
				(c.pi1 = pi1.id AND c.pi2 = pi2.id)
				OR
				(c.pi1 = pi2.id AND c.pi2 = pi1.id)
			WHERE c.uid = $_SESSION[uid]
				AND pi1.uid = $_SESSION[uid]
				AND pi2.uid = $_SESSION[uid]
				AND pi1.edible != 0 
				AND pi2.edible != 0
				AND pi1.id != pi2.id
				AND c.answer != 1
			ORDER BY ISNULL(c.answer)
		") or die('Database error 812599: '.$db->error);
	}

	if ($unmatchedItems->num_rows > 0) {
		list($pi1id, $pi1name, $pi2id, $pi2name) = $unmatchedItems->fetch_row();
		print("<form method=post action='?combinations'>");
		print("<input type=hidden name=pi1id value=$pi1id>");
		print("<input type=hidden name=pi2id value=$pi2id>");
		print("Are <strong>" . htmlescape($pi1name) . "</strong> and <strong>" . htmlescape($pi2name) . "</strong> a good combination?<br>");
		print("<input type=submit name=answer value=Yes> ");
		print("<input type=submit name=answer value='Absolutely not'> ");
		print("<input type=submit name=answer value='Maybe (skip for now)'>");
		print("</form>");
	}

	print('<br><br><br>');

	$goodideas = $db->query("
		SELECT pi1.item, pi2.item
		FROM combinations c
		INNER JOIN popularitems pi1
			ON pi1.id = c.pi1
		INNER JOIN popularitems pi2
			ON pi2.id = c.pi2
		WHERE c.answer = 2
			AND c.uid = $_SESSION[uid]
			AND pi1.uid = $_SESSION[uid]
			AND pi2.uid = $_SESSION[uid]
		ORDER BY RAND()
		") or die('Database error 1213134');
	while ($row = $goodideas->fetch_row()) {
		print("Idea: " . htmlescape($row[0]) . " and " . htmlescape($row[1]) . "!<br>");
	}

