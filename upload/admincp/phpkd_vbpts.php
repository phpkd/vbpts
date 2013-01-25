<?php
/*==================================================================================*\
|| ################################################################################ ||
|| # Product Name: vB Product Translation System                 Version: 4.2.110 # ||
|| # License Type: Free License                                                   # ||
|| # ---------------------------------------------------------------------------- # ||
|| # 																			  # ||
|| #            Copyright ©2005-2013 PHP KingDom. All Rights Reserved.            # ||
|| #       This product may be redistributed in whole or significant part.        # ||
|| # 																			  # ||
|| # ------------ "vB Product Translation System" IS A FREE SOFTWARE ------------ # ||
|| #        http://www.phpkd.net | http://info.phpkd.net/en/license/free/         # ||
|| ################################################################################ ||
\*==================================================================================*/


// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('language');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/adminfunctions_language.php');
require_once(DIR . '/includes/functions_misc.php');
require_once(DIR . '/includes/functions_phpkd_vbpts.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminlanguages'))
{
	print_cp_no_permission();
}

$vbulletin->input->clean_array_gpc('r', array(
	'phraseid' => TYPE_INT,
));

// ############################# LOG ACTION ###############################
log_admin_action(iif($vbulletin->GPC['phraseid'], "phrase id = " . $vbulletin->GPC['phraseid']));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'main';
}

// #############################################################################
if ($_POST['do'] != 'download')
{
	print_cp_header($vbphrase['phpkd_vbpts']);
}


// #############################################################################
if ($_REQUEST['do'] == 'modify')
{
	echo '<script type="text/javascript" src="../clientscript/jquery/jquery-1.9.0.min.js?v=' . SIMPLE_VERSION . '"></script>
<script type="text/javascript" src="../clientscript/jquery/jquery.chained.js?v=' . SIMPLE_VERSION . '"></script>

<script type="text/javascript">
function copy_default_text(srclang, dstlang, phrase)
{
	var deftext = fetch_object(\'text_\' + phrase + \'_\' + srclang).value;
	if (deftext == "")
	{
		alert("' . $vbphrase['default_text_is_empty'] . '");
	}
	else
	{
		fetch_object(\'text_\' + phrase + \'_\' + dstlang).value = deftext;
	}

	return false;
}

function copy_suggested_text(lang, phrase)
{
	var deftext = fetch_object(\'suggested_\' + phrase + \'_\' + lang).value;
	if (deftext == "")
	{
		alert("' . $vbphrase['default_text_is_empty'] . '");
	}
	else
	{
		fetch_object(\'text_\' + phrase + \'_\' + lang).value = deftext;
	}

	return false;
}
</script>
';

	$vbulletin->input->clean_array_gpc('r', array(
		'product'        => TYPE_STR,
		'product2'        => TYPE_STR,
		'srclanguageid'  => TYPE_INT,
		'dstlanguageid'  => TYPE_ARRAY_INT,
		'phrasetype'     => TYPE_NOHTML,
		'phrasetype2'     => TYPE_NOHTML,
		'filter'         => TYPE_INT,
		'recentupdate'   => TYPE_BOOL,
		'perpage'        => TYPE_INT,
		'pagenumber'     => TYPE_INT,
	));


	$products = fetch_product_list();
	$languages = fetch_languages_array();
	$phrasetypes = fetch_phrasetypes_array();
	$srclanguages = array(-1 => array('languageid' => -1, 'title' => 'MASTER LANGUAGE')) + $languages;
	$dstlanguages = $languages;

	$phrasetypes_sql1 = '';
	$phrasetypes_sql2 = '';
	$phrasetypes_sql3 = 'GROUP BY ';

	foreach ($products as $productid => $producttitle)
	{
		$phrasetypes_sql1 .= ', ' . $productid . '.fieldname AS ' . $productid;
		$phrasetypes_sql2 .= ' LEFT JOIN '. TABLE_PREFIX . 'phrasetype AS ' . $productid . ' ON (' . $productid . '.fieldname = phrase.fieldname AND phrase.product = \'' . $productid . '\')';
		$phrasetypes_sql3 .= $productid . ', ';
	}

	$phrasetypes_query = $db->query_read("
		SELECT " . substr($phrasetypes_sql1, 2) . "
		FROM " . TABLE_PREFIX . "phrase AS phrase
			$phrasetypes_sql2
			WHERE phrase.languageid IN (-1, 0)
			" . substr($phrasetypes_sql3, 0, -2)
	);

	while ($phrasetype_result = $db->fetch_array($phrasetypes_query))
	{
		foreach ($products as $productid => $producttitle)
		{
			if (!empty($phrasetype_result[$productid]))
			{
				$product_phrasetype[$productid][] = $phrasetype_result[$productid];
			}
		}
	}


	$y = 0;
	ksort($product_phrasetype);
	$productmenulist = '<select id="product" name="product" class="bginput">';
	$phrasetypemenulist = '<select id="phrasetype" name="phrasetype" class="bginput">';

	foreach ($product_phrasetype AS $productid => $phrasetypearr)
	{
		$productmenulist .= '<option value="' . $productid . '" ' . (($vbulletin->GPC['product'] == $productid) ? 'selected="selected"' : '') . '>' . $products[$productid] . '</option>';
		$phrasetypemenulist .= '<option value="0" class="' . $productid . '"></option>';

		foreach ($phrasetypearr AS $phrasetypeid => $phrasetype)
		{
			$y++;
			$phrasetypemenulist .= '<option value="' . $phrasetype . '" class="' . $productid . '" ' . (($vbulletin->GPC['phrasetype'] == $phrasetype AND $vbulletin->GPC['product'] == $productid) ? 'selected="selected"' : '') . '>' . $phrasetypes[$phrasetype]['title'] . '</option>';
		}

	}

	$productmenulist .= '</select>';
	$phrasetypemenulist .= '</select>';


	if (empty($products["{$vbulletin->GPC['product']}"]))
	{
		print_stop_message('phpkd_vbpts_invalid_product');
	}

	if (empty($srclanguages["{$vbulletin->GPC['srclanguageid']}"]))
	{
		print_stop_message('phpkd_vbpts_invalid_srclanguage');
	}

	if (!empty($vbulletin->GPC['dstlanguageid']))
	{
		foreach ($dstlanguages as $languageid => $language)
		{
			if (strpos(',' . implode(',', $vbulletin->GPC['dstlanguageid']) . ',', ",$languageid,") === false)
			{
				unset($dstlanguages["{$languageid}"]);
			}
		}
	}
	else
	{
		print_stop_message('phpkd_vbpts_invalid_dstlanguage');
	}

	if (empty($dstlanguages))
	{
		print_stop_message('phpkd_vbpts_invalid_dstlanguage');
	}

	if (empty($vbulletin->GPC['phrasetype']))
	{
		$vbulletin->GPC['phrasetype'] = 0;
	}

	if (empty($vbulletin->GPC['product2']))
	{
		$vbulletin->GPC['product2'] = $vbulletin->GPC['product'];
	}

	// check display values are valid
	if ($vbulletin->GPC['perpage'] < 1)
	{
		$vbulletin->GPC['perpage'] = 10;
	}

	if ($vbulletin->GPC['pagenumber'] < 1 OR $vbulletin->GPC['product'] != $vbulletin->GPC['product2'] OR $vbulletin->GPC['phrasetype'] != $vbulletin->GPC['phrasetype2'])
	{
		$vbulletin->GPC['pagenumber'] = 1;
	}


	$cphrases = array();

	switch ($vbulletin->GPC['filter'])
	{
		case 1:
			$phrasetable = 'orphan';
			break;

		case 2:
			$phrasetable = 'parent';
			break;

		default:
			$phrasetable = 'phrase';
			break;
	}


	$phrasefield = '';
	$phrasejoin = '';
	foreach ($dstlanguages AS $dstlanguageid => $dstlanguage)
	{
		$phrasefield .= ",NOT ISNULL(l{$dstlanguageid}.varname) AS l{$dstlanguageid}found, l{$dstlanguageid}.version AS l{$dstlanguageid}version, l{$dstlanguageid}.phraseid AS l{$dstlanguageid}phraseid, l{$dstlanguageid}.text AS l{$dstlanguageid}text\n";
		$phrasejoin .= "LEFT JOIN " . TABLE_PREFIX . "phrase AS l{$dstlanguageid} ON (l{$dstlanguageid}.varname = {$phrasetable}.varname AND l{$dstlanguageid}.fieldname = {$phrasetable}.fieldname AND l{$dstlanguageid}.languageid = {$dstlanguageid})\n\t";
	}


	switch ($vbulletin->GPC['filter'])
	{
		// Orphan = Phrase exists in translation but not in parent global language
		case 1:
			$phrases = $db->query_read("
				SELECT orphan.phraseid, orphan.languageid, orphan.varname, orphan.fieldname, orphan.text
				$phrasefield
				FROM " . TABLE_PREFIX . "phrase AS orphan
				$phrasejoin
				LEFT JOIN " . TABLE_PREFIX . "phrase AS parent ON (parent.languageid IN(-1, 0) AND parent.varname = orphan.varname AND parent.fieldname = orphan.fieldname)
				WHERE orphan.product = '" . $db->escape_string($vbulletin->GPC['product']) . "'
					AND orphan.languageid = " . $vbulletin->GPC['srclanguageid'] . "
					AND parent.phraseid IS NULL
					" . iif(!empty($vbulletin->GPC['phrasetype']), "AND orphan.fieldname = '" . $vbulletin->GPC['phrasetype'] . "'") . "
					" . iif(!empty($vbulletin->GPC['recentupdate']), "AND orphan.dateline > " . TIMENOW - 86400 . "')") . "
				ORDER BY orphan.fieldname, orphan.varname
				LIMIT " . (($vbulletin->GPC['pagenumber'] - 1) * $vbulletin->GPC['perpage']) . ", " . $vbulletin->GPC['perpage'] . "
			");
			break;

		// Missing = Phrase exists in parent global language but not in translation
		case 2:
			$phrases = $db->query_read("
				SELECT parent.phraseid, parent.languageid, parent.varname, parent.fieldname, parent.text
				$phrasefield
				FROM " . TABLE_PREFIX . "phrase AS parent
				$phrasejoin
				LEFT JOIN " . TABLE_PREFIX . "phrase AS missing ON (missing.languageid = " . $vbulletin->GPC['srclanguageid'] . " AND missing.varname = parent.varname AND missing.fieldname = parent.fieldname)
				WHERE parent.product = '" . $db->escape_string($vbulletin->GPC['product']) . "'
					AND parent.languageid IN (-1, 0)
					AND missing.phraseid IS NULL
					" . iif(!empty($vbulletin->GPC['phrasetype']), "AND parent.fieldname = '" . $vbulletin->GPC['phrasetype'] . "'") . "
					" . iif(!empty($vbulletin->GPC['recentupdate']), "AND parent.dateline > " . TIMENOW - 86400 . "')") . "
				ORDER BY parent.fieldname, parent.varname
				LIMIT " . (($vbulletin->GPC['pagenumber'] - 1) * $vbulletin->GPC['perpage']) . ", " . $vbulletin->GPC['perpage'] . "
			");
			break;

		// All Phrases
		default:
			$phrases = $db->query_read("
				SELECT phrase.phraseid, phrase.languageid, phrase.varname, phrase.fieldname, phrase.text
				$phrasefield
				FROM " . TABLE_PREFIX . "phrase AS phrase
				$phrasejoin
				WHERE phrase.product = '" . $db->escape_string($vbulletin->GPC['product']) . "'
					AND phrase.languageid = " . $vbulletin->GPC['srclanguageid'] . "
					" . iif(!empty($vbulletin->GPC['phrasetype']), "AND phrase.fieldname = '" . $vbulletin->GPC['phrasetype'] . "'") . "
					" . iif(!empty($vbulletin->GPC['recentupdate']), "AND phrase.dateline > " . TIMENOW - 86400 . "')") . "
				ORDER BY phrase.fieldname, phrase.varname
				LIMIT " . (($vbulletin->GPC['pagenumber'] - 1) * $vbulletin->GPC['perpage']) . ", " . $vbulletin->GPC['perpage'] . "
			");
			break;
	}

	while ($phrase = $db->fetch_array($phrases))
	{
		$cphrases["{$phrase['fieldname']}"]["{$phrase['varname']}"]["{$phrase['languageid']}"] = $phrase;
	}
	unset($phrase);
	$db->free_result($phrases);

	if (empty($cphrases))
	{
		print_stop_message('phpkd_vbpts_invalid_dstlanguage2');
	}

	// Stats - Global: Destination Language(s) Info
	$stats_dstlanguage_info = $db->query_read("
		SELECT * FROM " . TABLE_PREFIX . "language AS language
		WHERE languageid IN (" . implode(',', array_keys($dstlanguages)) . ")
	");

	// Stats: 1) Phrasetypes Used 2) Specific Phrasetypes 3) Main Language Phrases
	$stats_phrases = $db->query_first("
		SELECT COUNT(phrase.phraseid) AS phrases, COUNT(Distinct phrase.fieldname) AS phrasetypes, COUNT(Distinct phrasetype.fieldname) AS phrasetype_specifics
		FROM " . TABLE_PREFIX . "phrase AS phrase
		LEFT JOIN " . TABLE_PREFIX . "phrasetype AS phrasetype ON (phrasetype.fieldname = phrase.fieldname AND phrasetype.product = '" . $db->escape_string($vbulletin->GPC['product']) . "')
		WHERE phrase.product = '" . $db->escape_string($vbulletin->GPC['product']) . "'
			AND phrase.languageid IN (-1,0)
	");


	// Stats: Destination Translation(s) Phrases
	$stats_phrases_dst_sql = '';
	foreach ($dstlanguages as $dstlanguageid => $dstlanguage)
	{
		$stats_phrases_dst_sql .= ', SUM(IF(phrase.languageid = ' . $dstlanguageid . ', 1,0)) AS l' . $dstlanguageid;
	}

	$stats_phrases_dst = $db->query_first("
		SELECT " . substr($stats_phrases_dst_sql, 2) . "
		FROM " . TABLE_PREFIX . "phrase AS phrase
		WHERE phrase.product = '" . $db->escape_string($vbulletin->GPC['product']) . "'
			AND phrase.languageid IN (" . implode(',', array_keys($dstlanguages)) . ")
	");


	// Stats: Destination Translation(s) Missing Phrases
	$stats_phrases_dst_missing_sql1 = '';
	$stats_phrases_dst_missing_sql2 = '';
	foreach ($dstlanguages as $dstlanguageid => $dstlanguage)
	{
		$stats_phrases_dst_missing_sql1 .= ', SUM(IF(l' . $dstlanguageid . '.languageid = ' . $dstlanguageid . ', 0, 1)) AS l' . $dstlanguageid;
		$stats_phrases_dst_missing_sql2 .= ' LEFT JOIN '. TABLE_PREFIX . 'phrase AS l' . $dstlanguageid . ' ON (l' . $dstlanguageid . '.languageid = ' . $dstlanguageid . ' AND l' . $dstlanguageid . '.varname = parent.varname AND l' . $dstlanguageid . '.fieldname = parent.fieldname)';
	}

	$stats_phrases_dst_missing = $db->query_first("
		SELECT " . substr($stats_phrases_dst_missing_sql1, 2) . "
		FROM " . TABLE_PREFIX . "phrase AS parent
			$stats_phrases_dst_missing_sql2
			WHERE parent.product = '" . $db->escape_string($vbulletin->GPC['product']) . "'
			AND parent.languageid IN (-1, 0)
	");


	// Stats: Destination Translation(s) Orphan Phrases
	$stats_phrases_dst_orphan_sql = '';
	foreach ($dstlanguages as $dstlanguageid => $dstlanguage)
	{
		$stats_phrases_dst_orphan_sql .= ', SUM(IF(orphan.languageid = ' . $dstlanguageid . ', 1, 0)) AS l' . $dstlanguageid;
	}

	$stats_phrases_dst_orphan = $db->query_first("
		SELECT " . substr($stats_phrases_dst_orphan_sql, 2) . "
		FROM " . TABLE_PREFIX . "phrase AS orphan
		LEFT JOIN " . TABLE_PREFIX . "phrase AS parent ON (parent.languageid IN(-1, 0) AND parent.varname = orphan.varname AND parent.fieldname = orphan.fieldname)
		WHERE orphan.product = '" . $db->escape_string($vbulletin->GPC['product']) . "'
			AND orphan.languageid IN (" . implode(',', array_keys($dstlanguages)) . ")
			AND parent.phraseid IS NULL
	");


	// Stats: 1) New Phrases Added Today To Main Language 2) New Phrases Updated Today To Destination Translation(s)
	$stats_phrases_today_sql = '';
	foreach ($dstlanguages as $dstlanguageid => $dstlanguage)
	{
		$stats_phrases_today_sql .= ', SUM(IF(phrase.languageid = ' . $dstlanguageid . ', 1, 0)) AS l' . $dstlanguageid;
	}

	$stats_phrases_today = $db->query_first("
		SELECT SUM(IF(phrase.languageid IN(-1,0), 1, 0)) AS phrases
		$stats_phrases_today_sql
		FROM " . TABLE_PREFIX . "phrase AS phrase
		WHERE phrase.product = '" . $db->escape_string($vbulletin->GPC['product']) . "'
			AND phrase.languageid IN (-1,0," . implode(',', array_keys($dstlanguages)) . ")
			AND dateline > " . (TIMENOW - 86400) . "
	");


	// Stats - Global: Prepare the output string
	while ($stats_dstlanguage_single = $db->fetch_array($stats_dstlanguage_info))
	{
		$stats_phrases_dst_str[] = $stats_dstlanguage_single['title'] . ' (' . vb_number_format($stats_phrases_dst['l' . $stats_dstlanguage_single['languageid']]) . ')';
		$stats_phrases_dst_missing_str[] = $stats_dstlanguage_single['title'] . ' (' . vb_number_format($stats_phrases_dst_missing['l' . $stats_dstlanguage_single['languageid']]) . ')';
		$stats_phrases_dst_orphan_str[] = $stats_dstlanguage_single['title'] . ' (' . vb_number_format($stats_phrases_dst_orphan['l' . $stats_dstlanguage_single['languageid']]) . ')';
		$stats_phrases_today_str[] = $stats_dstlanguage_single['title'] . ' (' . vb_number_format($stats_phrases_today['l' . $stats_dstlanguage_single['languageid']]) . ')';
	}


	if ($vbulletin->GPC['product'] != 'vbulletin')
	{
		$productinfo = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "product AS product WHERE productid = '" . $db->escape_string($vbulletin->GPC['product']) . '\'');
	}
	else
	{
		$productinfo['title'] = 'vBulletin';
		$productinfo['productid'] = 'vbulletin';
	}


	// count phrases
	switch ($vbulletin->GPC['filter'])
	{
		// Orphan = Phrase exists in translation but not in parent global language
		case 1:
			if ($vbulletin->GPC['srclanguageid'] == -1 OR $vbulletin->GPC['srclanguageid'] == 0)
			{
				print_stop_message('phpkd_vbpts_invalid_srclanguage_orphan');
			}

			$countphrases = $db->query_first("
				SELECT COUNT(*) AS total
				FROM " . TABLE_PREFIX . "phrase AS orphan
				LEFT JOIN " . TABLE_PREFIX . "phrase AS parent ON (parent.languageid IN(-1, 0) AND parent.varname = orphan.varname AND parent.fieldname = orphan.fieldname)
				WHERE orphan.product = '" . $db->escape_string($vbulletin->GPC['product']) . "'
					AND orphan.languageid = " . $vbulletin->GPC['srclanguageid'] . "
					AND parent.phraseid IS NULL
					" . iif(!empty($vbulletin->GPC['phrasetype']), "AND orphan.fieldname = '" . $vbulletin->GPC['phrasetype'] . "'") . "
					" . iif(!empty($vbulletin->GPC['recentupdate']), "AND orphan.dateline > " . TIMENOW - 86400 . "')") . "
			");
			break;

		// Missing = Phrase exists in parent global language but not in translation
		case 2:
			if ($vbulletin->GPC['srclanguageid'] == -1 OR $vbulletin->GPC['srclanguageid'] == 0)
			{
				print_stop_message('phpkd_vbpts_invalid_srclanguage_missing');
			}

			$countphrases = $db->query_first("
				SELECT COUNT(*) AS total
				FROM " . TABLE_PREFIX . "phrase AS parent
				LEFT JOIN " . TABLE_PREFIX . "phrase AS missing ON (missing.languageid = " . $vbulletin->GPC['srclanguageid'] . " AND missing.varname = parent.varname AND missing.fieldname = parent.fieldname)
				WHERE parent.product = '" . $db->escape_string($vbulletin->GPC['product']) . "'
					AND parent.languageid IN (-1, 0)
					AND missing.phraseid IS NULL
					" . iif(!empty($vbulletin->GPC['phrasetype']), "AND parent.fieldname = '" . $vbulletin->GPC['phrasetype'] . "'") . "
					" . iif(!empty($vbulletin->GPC['recentupdate']), "AND parent.dateline > " . TIMENOW - 86400 . "')") . "
			");
			break;

		// All phrases
		default:
			$countphrases = $db->query_first("
				SELECT COUNT(*) AS total
				FROM " . TABLE_PREFIX . "phrase AS phrase
				WHERE phrase.product = '" . $db->escape_string($vbulletin->GPC['product']) . "'
					AND phrase.languageid = " . $vbulletin->GPC['srclanguageid'] . "
					" . iif(!empty($vbulletin->GPC['phrasetype']), "AND phrase.fieldname = '" . $vbulletin->GPC['phrasetype'] . "'") . "
					" . iif(!empty($vbulletin->GPC['recentupdate']), "AND phrase.dateline > " . TIMENOW - 86400 . "')") . "
			");
			break;
	}

	$numphrases =& $countphrases['total'];
	$numpages = ceil($numphrases / $vbulletin->GPC['perpage']);

	if ($numpages < 1)
	{
		$numpages = 1;
	}
	if ($vbulletin->GPC['pagenumber'] > $numpages)
	{
		$vbulletin->GPC['pagenumber'] = $numpages;
	}

	$showprev = false;
	$shownext = false;

	if ($vbulletin->GPC['pagenumber'] > 1)
	{
		$showprev = true;
	}
	if ($vbulletin->GPC['pagenumber'] < $numpages)
	{
		$shownext = true;
	}

	$pageoptions = array();
	for ($i = 1; $i <= $numpages; $i++)
	{
		$pageoptions["$i"] = "$vbphrase[page] $i / $numpages";
	}

	print_form_header('phpkd_vbpts', 'modify', false, true, 'navform', '90%', '', true, 'get');
	construct_hidden_code('product2', $vbulletin->GPC['product']);
	construct_hidden_code('phrasetype2', $vbulletin->GPC['phrasetype']);
	construct_hidden_code('srclanguageid', $vbulletin->GPC['srclanguageid']);
	construct_hidden_code('filter', $vbulletin->GPC['filter']);
	construct_hidden_code('recentupdate', $vbulletin->GPC['recentupdate']);

	foreach ($dstlanguages as $dstlanguageid => $dstlanguage)
	{
		// Don't use construct_hidden_code() because it does NOT embed multiple fields with the same name 'dstlanguageid[]'
		echo '<input type="hidden" name="dstlanguageid[]" value="' . $dstlanguageid . '" />';
	}

	print_table_header(construct_phrase($vbphrase['phpkd_vbpts_productstats'], $productinfo['title'], $productinfo['productid']), 4);
	print_cells_row(array(
		"Product", $productmenulist,
		"Phrasetype", $phrasetypemenulist
	), 0, 0, -5, 'top', 1, 1);
	print_cells_row(array(
		$vbphrase['phpkd_vbpts_stats_phrasetype_used'], vb_number_format($stats_phrases['phrasetypes']),
		$vbphrase['phpkd_vbpts_stats_phrasetype_specific'], vb_number_format($stats_phrases['phrasetypes_specific'])
	), 0, 0, -5, 'top', 1, 1);
	print_cells_row(array(
		$vbphrase['phpkd_vbpts_stats_phrase_parent'], vb_number_format($stats_phrases['phrases']),
		$vbphrase['phpkd_vbpts_stats_phrase_translated'], implode(', ', $stats_phrases_dst_str)
	), 0, 0, -5, 'top', 1, 1);
	print_cells_row(array(
		$vbphrase['phpkd_vbpts_stats_phrase_missing'], implode(', ', $stats_phrases_dst_missing_str),
		$vbphrase['phpkd_vbpts_stats_phrase_orphan'], implode(', ', $stats_phrases_dst_orphan_str)
	), 0, 0, -5, 'top', 1, 1);
	print_cells_row(array(
		$vbphrase['phpkd_vbpts_stats_phrase_parent_today'], vb_number_format($stats_phrases_today['phrases']),
		$vbphrase['phpkd_vbpts_stats_phrase_translated_today'], implode(', ', $stats_phrases_today_str)
	), 0, 0, -5, 'top', 1, 1);

	echo '
	<tr>
		<td class="thead">' . $vbphrase['phpkd_vbpts_page'] . ':</td>
		<td class="thead">' .
			'<input type="button"' . iif(!$showprev, ' disabled="disabled"') . ' class="button" value="&laquo; ' . $vbphrase['prev'] . '" tabindex="1" onclick="this.form.page.selectedIndex -= 1; this.form.submit()" />' .
			'<select name="page" tabindex="1" onchange="this.form.submit()" class="bginput">' . construct_select_options($pageoptions, $vbulletin->GPC['pagenumber']) . '</select>' .
			'<input type="button"' . iif(!$shownext, ' disabled="disabled"') . ' class="button" value="' . $vbphrase['next'] . ' &raquo;" tabindex="1" onclick="this.form.page.selectedIndex += 1; this.form.submit()" />
		</td>
		<td class="thead">' . $vbphrase['phrases_to_show_per_page'] . ':</td>
		<td class="thead"><input type="text" class="bginput" name="perpage" value="' . $vbulletin->GPC['perpage'] . '" tabindex="1" size="5" />
		<input type="submit" class="button" value=" ' . $vbphrase['go'] . ' " tabindex="1" accesskey="s" /></td>
	</tr>';
	print_table_footer();

	echo '<script type="text/javascript">$(function(){$("#phrasetype").chained("#product");});</script>';

	print_form_header('phpkd_vbpts', 'update', false, true, 'phraseform', '90%', '', true, 'post', 1);
	construct_hidden_code('product', $vbulletin->GPC['product']);
	construct_hidden_code('phrasetype', $vbulletin->GPC['phrasetype']);
	construct_hidden_code('srclanguageid', $vbulletin->GPC['srclanguageid']);
	construct_hidden_code('filter', $vbulletin->GPC['filter']);
	construct_hidden_code('recentupdate', $vbulletin->GPC['recentupdate']);
	construct_hidden_code('pagenumber', $vbulletin->GPC['pagenumber']);
	construct_hidden_code('perpage', $vbulletin->GPC['perpage']);

	foreach ($dstlanguages as $dstlanguageid => $dstlanguage)
	{
		// Don't use construct_hidden_code() because it does NOT embed multiple fields with the same name 'dstlanguageid[]'
		echo '<input type="hidden" name="dstlanguageid[]" value="' . $dstlanguageid . '" />';
	}

	foreach($cphrases AS $_fieldname => $varnames)
	{
		ksort($varnames);
		foreach($varnames AS $varname => $phrase)
		{
			$translationarray['text'][] = htmlentities($phrase[$vbulletin->GPC['srclanguageid']]['text']);
		}
	}

	if (empty($translationarray['text']) OR empty($vbulletin->options['phpkd_vbpts_suggestion_clientid']) OR empty($vbulletin->options['phpkd_vbpts_suggestion_clientsecret']))
	{
		$vbulletin->options['phpkd_vbpts_suggestion_active'] = 0;
	}

	if ($vbulletin->options['phpkd_vbpts_suggestion_active'])
	{
		$phpkd_vbpts_suggestion_active = 1;

		// Soap WSDL Url
		$wsdlUrl       = "http://api.microsofttranslator.com/V2/Soap.svc";

		// Client ID of the application.
		$clientID       = $vbulletin->options['phpkd_vbpts_suggestion_clientid'];

		// Client Secret key of the application.
		$clientSecret = $vbulletin->options['phpkd_vbpts_suggestion_clientsecret'];

		// OAuth Url.
		$authUrl      = "https://datamarket.accesscontrol.windows.net/v2/OAuth2-13/";

		// Application Scope Url
		$scopeUrl     = "http://api.microsofttranslator.com";

		// Application grant type
		$grantType    = "client_credentials";

		// Create the Authentication object
		$authObj      = new AccessTokenAuthentication();

		// Get the Access token
		$accessToken = $authObj->getTokens($grantType, $scopeUrl, $clientID, $clientSecret, $authUrl);

		if (empty($accessToken))
		{
			$phpkd_vbpts_suggestion_active = 0;
			$vbulletin->options['phpkd_vbpts_suggestion_active'] = 0;
		}

		if ($phpkd_vbpts_suggestion_active)
		{
			// Create soap translator Object
			$soapTranslator = new SOAPMicrosoftTranslator($accessToken, $wsdlUrl);

			// Optional argument list.
			$optionArg = array (
				'category'    => "general",
				'ContentType' => "text/plain",
				'Uri'         => 'all',
				'User'        => 'all'
			);

			$srclanguageid = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "language WHERE languageid = " . ($vbulletin->GPC['srclanguageid'] <= 0 ? 1 : $vbulletin->GPC['srclanguageid']));

			foreach ($dstlanguages as $dstlanguageid => $dstlanguage)
			{
				$translationarray['fromto'][] = array('from' => $srclanguageid['languagecode'], 'fromid' => $srclanguageid['languageid'], 'to' => $dstlanguage['languagecode'], 'toid' => $dstlanguageid);
			}

			foreach ($translationarray['fromto'] as $translation)
			{
				$response[$translation['toid']] = $soapTranslator->objSoap->TranslateArray(array(
					'texts'   => $translationarray['text'],
					'from'    => $translation['from'],
					'to'      => $translation['to'],
					'options' => $optionArg
				));
			}
		}
	}

	$z = 0;

	// show phrases
	foreach($cphrases AS $_fieldname => $varnames)
	{
		print_table_header(construct_phrase($vbphrase['x_phrases'], $phrasetypes["$_fieldname"]) . " <span class=\"normal\">(fieldname = $_fieldname)</span>", ($vbulletin->options['phpkd_vbpts_suggestion_active'] ? 3 : 2));

		if ($vbulletin->options['phpkd_vbpts_suggestion_active'])
		{
			print_cells_row(
				array(
					$vbphrase['varname'],
					$vbphrase['text'],
					$vbphrase['phpkd_vbpts_suggestion']
				), 1, 0, -3
			);
		}
		else
		{
			print_cells_row(
				array(
					$vbphrase['varname'],
					$vbphrase['text']
				), 1, 0, -2
			);
		}


		ksort($varnames);
		foreach($varnames AS $varname => $phrase)
		{
			$text = "<span class=\"smallfont\" style=\"font-weight: bold\">$vbphrase[phpkd_vbpts_defaulttext]</span><div class=\"{$altclass}\" style=\"padding:4px; border:inset 1px;\"><span class=\"smallfont\">" . nl2br(htmlspecialchars_uni($phrase[$vbulletin->GPC['srclanguageid']]['text'])) . "</span></div><br />";

			if ($vbulletin->options['phpkd_vbpts_suggestion_active'])
			{
				$suggested = $text;
			}

			echo '<input type="hidden" name="text_' . $phrase[$vbulletin->GPC['srclanguageid']]['phraseid'] . '_' . ($vbulletin->GPC['srclanguageid'] < 0 ? 0 : $vbulletin->GPC['srclanguageid']) . '" id="text_' . $phrase[$vbulletin->GPC['srclanguageid']]['phraseid'] . '_' . ($vbulletin->GPC['srclanguageid'] < 0 ? 0 : $vbulletin->GPC['srclanguageid']) . '" value="' . nl2br(htmlspecialchars_uni($phrase[$vbulletin->GPC['srclanguageid']]['text'])) . '" />';

			foreach ($dstlanguages as $dstlanguageid => $dstlanguage)
			{
				construct_hidden_code("phrase[$dstlanguageid][" . $phrase[$vbulletin->GPC['srclanguageid']]['fieldname'] . "][" . $phrase[$vbulletin->GPC['srclanguageid']]['varname'] . "][default]", $phrase[$vbulletin->GPC['srclanguageid']]["l{$dstlanguageid}text"]);
				$resizer = "<div class=\"smallfont\"><a href=\"#\" onclick=\"return resize_textarea(1, 'text_" . $phrase[$vbulletin->GPC['srclanguageid']]['phraseid'] . "_$dstlanguageid')\">$vbphrase[increase_size]</a> <a href=\"#\" onclick=\"return resize_textarea(-1, 'text_" . $phrase[$vbulletin->GPC['srclanguageid']]['phraseid'] . "_$dstlanguageid')\">$vbphrase[decrease_size]</a> <a href=\"#\" onclick=\"return copy_default_text(" . ($vbulletin->GPC['srclanguageid'] < 0 ? 0 : $vbulletin->GPC['srclanguageid']) . ", " . $dstlanguageid . ", " . $phrase[$vbulletin->GPC['srclanguageid']]['phraseid'] . ")\">$vbphrase[copy_default_text]</a> <a href=\"#\" onclick=\"return copy_suggested_text(" . $dstlanguageid . ", " . $phrase[$vbulletin->GPC['srclanguageid']]['phraseid'] . ")\">$vbphrase[phpkd_vbpts_copy_suggested]</a></div>";
				$text .= "<div class=\"smallfont\"><span style=\"font-weight: bold\">$dstlanguage[title]</span>" . ($phrase[$vbulletin->GPC['srclanguageid']]['l' . $dstlanguageid . 'found'] ?  "<span class=\"smallfont\"><label for=\"revert" . $phrase[$vbulletin->GPC['srclanguageid']]['l' . $dstlanguageid . 'phraseid'] . "\"><input type=\"checkbox\" name=\"phrase[$dstlanguageid][" . $phrase[$vbulletin->GPC['srclanguageid']]['fieldname'] . "][" . $phrase[$vbulletin->GPC['srclanguageid']]['varname'] . "][revert]\" id=\"revert" . $phrase[$vbulletin->GPC['srclanguageid']]['l' . $dstlanguageid . 'phraseid'] . "\" value=\"" . $phrase[$vbulletin->GPC['srclanguageid']]['l' . $dstlanguageid . 'phraseid'] . "\" tabindex=\"1\" />$vbphrase[revert]</label></span>" : '') . "<br />
				<textarea id=\"text_" . $phrase[$vbulletin->GPC['srclanguageid']]['phraseid'] . "_$dstlanguageid\" class=\"code-" . iif($phrase[$vbulletin->GPC['srclanguageid']]["l{$dstlanguageid}found"], 'c', 'g') . "\" name=\"phrase[$dstlanguageid][" . $phrase[$vbulletin->GPC['srclanguageid']]['fieldname'] . "][" . $phrase[$vbulletin->GPC['srclanguageid']]['varname'] . "][text]\" rows=\"3\" cols=\"60\" tabindex=\"1\" dir=\"$dstlanguage[direction]\">" . htmlspecialchars_uni($phrase[$vbulletin->GPC['srclanguageid']]["l{$dstlanguageid}text"]) . "</textarea>$resizer</div><br />";

				if ($vbulletin->options['phpkd_vbpts_suggestion_active'])
				{
					$suggested .= "<div class=\"smallfont\"><span style=\"font-weight: bold\">$dstlanguage[title]</span><br /><textarea id=\"suggested_" . $phrase[$vbulletin->GPC['srclanguageid']]['phraseid'] . "_$dstlanguageid\" class=\"code-g\" name=\"suggested[$dstlanguageid][" . $phrase[$vbulletin->GPC['srclanguageid']]['fieldname'] . "][" . $phrase[$vbulletin->GPC['srclanguageid']]['varname'] . "][text]\" rows=\"3\" cols=\"60\" tabindex=\"1\" dir=\"$dstlanguage[direction]\">" . ((is_array($response[$dstlanguageid]->TranslateArrayResult->TranslateArrayResponse)) ? html_entity_decode($response[$dstlanguageid]->TranslateArrayResult->TranslateArrayResponse[$z]->TranslatedText) : html_entity_decode($response[$dstlanguageid]->TranslateArrayResult->TranslateArrayResponse->TranslatedText)) . "</textarea></div><br /><br />";
				}
			}

			if ($vbulletin->options['phpkd_vbpts_suggestion_active'])
			{
				print_cells_row(
					array(
						construct_wrappable_varname($varname, 'font-weight:bold;', 'smallfont', 'span'),
						$text,
						$suggested
					), 0, 0, -3
				);
			}
			else
			{
				print_cells_row(
					array(
						construct_wrappable_varname($varname, 'font-weight:bold;', 'smallfont', 'span'),
						$text
					), 0, 0, -2
				);
			}

			$z++;
		}
	}

	print_submit_row($vbphrase['save'], '_default_', ($vbulletin->options['phpkd_vbpts_suggestion_active'] ? 3 : 2));
	print_table_footer();
}


// ######################################################################################
if ($_POST['do'] == 'update')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'product'        => TYPE_STR,
		'srclanguageid'  => TYPE_INT,
		'dstlanguageid'  => TYPE_ARRAY_INT,
		'phrasetype'     => TYPE_NOHTML,
		'filter'         => TYPE_INT,
		'recentupdate'   => TYPE_BOOL,
		'perpage'        => TYPE_INT,
		'pagenumber'     => TYPE_INT,
		'phrase'         => TYPE_ARRAY,
	));

	if (empty($vbulletin->GPC['product']))
	{
		$vbulletin->GPC['product'] = 'vbulletin';
	}

	$updatelanguage = array();

	$sql = array();
	$deleteids = '';

	$productinfo = fetch_product_list(true);

	foreach ($vbulletin->GPC['phrase'] AS $langid => $phrasegroups)
	{
		foreach ($phrasegroups AS $fieldname => $phrases)
		{
			foreach ($phrases AS $varname => $phrase)
			{
				if ($phrase['revert'])
				{
					$deleteids .= ",$phrase[revert]";
					$updatelanguage["$langid"] = true;
				}
				else if ($phrase['text'] != $phrase['default'])
				{
					$sql[] = "
						(" . $langid . ",
						'" . $db->escape_string($fieldname) . "',
						'" . $db->escape_string($varname) . "',
						'" . $db->escape_string($phrase['text']) . "',
						'" . $db->escape_string($vbulletin->GPC['product']) . "',
						'" . $db->escape_string($vbulletin->userinfo['username']) . "',
						" . TIMENOW . ",
						'" . $db->escape_string($productinfo["{$vbulletin->GPC['product']}"]['version']) . "')
					";
					$updatelanguage["$langid"] = true;
				}
			}
		}
	}

	if (!empty($deleteids))
	{
		$db->query_write("DELETE FROM " . TABLE_PREFIX . "phrase WHERE phraseid IN(0$deleteids)");
	}

	if (!empty($sql))
	{
		$db->query_write("
			REPLACE INTO " . TABLE_PREFIX . "phrase
				(languageid, fieldname, varname, text, product, username, dateline, version)
			VALUES
				" . implode(", ", $sql) . "
		");
	}

	if (!empty($updatelanguage))
	{
		foreach (array_keys($updatelanguage) AS $langid)
		{
			build_language($langid);
		}
	}

	define('CP_REDIRECT', 'phpkd_vbpts.php?do=modify'  . '&amp;page=' . $vbulletin->GPC['pagenumber'] . '&amp;perpage=' . $vbulletin->GPC['perpage'] . '&amp;srclanguageid=' . $vbulletin->GPC['srclanguageid'] . (!empty($vbulletin->GPC['dstlanguageid']) ? '&amp;dstlanguageid[]=' . implode('&amp;dstlanguageid[]=', $vbulletin->GPC['dstlanguageid']) : '') . (!empty($vbulletin->GPC['phrasetype']) ? '&amp;phrasetype=' . $vbulletin->GPC['phrasetype'] : '') . '&amp;product=' . $vbulletin->GPC['product'] . '&amp;recentupdate=' . $vbulletin->GPC['recentupdate'] . '&amp;filter=' . $vbulletin->GPC['filter']);
	print_stop_message('phpkd_vbpts_saved_languages_successfully');
}


// #############################################################################
if ($_REQUEST['do'] == 'main')
{
	echo '<script type="text/javascript" src="../clientscript/jquery/jquery-1.9.0.min.js?v=' . SIMPLE_VERSION . '"></script>
<script type="text/javascript" src="../clientscript/jquery/jquery.chained.js?v=' . SIMPLE_VERSION . '"></script>';

	if (!isset($_REQUEST['srclanguageid']))
	{
		$_REQUEST['srclanguageid'] = -1;
	}

	$vbulletin->input->clean_array_gpc('r', array(
		'product'        => TYPE_STR,
		'srclanguageid'  => TYPE_INT,
		'dstlanguageid'  => TYPE_ARRAY_INT,
		'phrasetype'     => TYPE_NOHTML,
		'filter'         => TYPE_INT,
		'recentupdate'   => TYPE_BOOL,
	));

	// get all languages
	$languageselect = array(-1 => $vbphrase['master_language']);

	$languages = $db->query_read("SELECT title, languageid FROM " . TABLE_PREFIX . "language");
	while ($language = $db->fetch_array($languages))
	{
		$languageselect["$language[languageid]"] = $language['title'];
	}
	unset($language);
	$db->free_result($languages);


	$phrasetypes = fetch_phrasetypes_array();
	$products = fetch_product_list();

	$phrasetypes_sql1 = '';
	$phrasetypes_sql2 = '';
	$phrasetypes_sql3 = 'GROUP BY ';
	foreach ($products as $productid => $producttitle)
	{
		$phrasetypes_sql1 .= ', ' . $productid . '.fieldname AS ' . $productid;
		$phrasetypes_sql2 .= ' LEFT JOIN '. TABLE_PREFIX . 'phrasetype AS ' . $productid . ' ON (' . $productid . '.fieldname = phrase.fieldname AND phrase.product = \'' . $productid . '\')';
		$phrasetypes_sql3 .= $productid . ', ';
	}

	$phrasetypes_query = $db->query_read("
		SELECT " . substr($phrasetypes_sql1, 2) . "
		FROM " . TABLE_PREFIX . "phrase AS phrase
		$phrasetypes_sql2
		WHERE phrase.languageid IN (-1, 0)
		" . substr($phrasetypes_sql3, 0, -2)
	);

	while ($phrasetype_result = $db->fetch_array($phrasetypes_query))
	{
		foreach ($products as $productid => $producttitle)
		{
			if (!empty($phrasetype_result[$productid]))
			{
				$product_phrasetype[$productid][] = $phrasetype_result[$productid];
			}
		}
	}


	ksort($product_phrasetype);
	$productmenulist = '<select id="product" name="product" class="bginput">';
	$phrasetypemenulist = '<select id="phrasetype" name="phrasetype" class="bginput" size="10">';

	foreach ($product_phrasetype AS $productid => $phrasetypearr)
	{
		$productmenulist .= '<option value="' . $productid . '" ' . (($vbulletin->GPC['product'] == $productid) ? 'selected="selected"' : '') . '>' . $products[$productid] . '</option>';
		$phrasetypemenulist .= '<option value="0" class="' . $productid . '"></option>';

		foreach ($phrasetypearr AS $phrasetypeid => $phrasetype)
		{
			$phrasetypemenulist .= '<option value="' . $phrasetype . '" class="' . $productid . '" ' . (($vbulletin->GPC['phrasetype'] == $phrasetype AND $vbulletin->GPC['product'] == $productid) ? 'selected="selected"' : '') . '>' . $phrasetypes[$phrasetype]['title'] . '</option>';
		}

	}

	$productmenulist .= '</select>';
	$phrasetypemenulist .= '</select>';


	print_form_header('phpkd_vbpts', 'modify', false, true, 'cpform', '90%', '', true, 'get');
	print_table_header($vbphrase['phpkd_vbpts_criteria']);
	print_label_row($vbphrase['product'], $productmenulist);
	print_select_row($vbphrase['phpkd_vbpts_srclanguage'], 'srclanguageid', $languageselect, $vbulletin->GPC['srclanguageid']);
	print_dstlanguage_row($vbphrase['phpkd_vbpts_dstlanguage'], 'dstlanguageid', array_shift($languageselect), $vbulletin->GPC['dstlanguageid']);
	print_label_row($vbphrase['phpkd_vbpts_phrase_type'], $phrasetypemenulist);
	print_label_row($vbphrase['phpkd_vbpts_filter'], '
		<span class="smallfont">
			<label for="rb_filter_0"><input type="radio" name="filter" value="0" id="rb_filter_0" tabindex="1" ' . iif(!isset($vbulletin->GPC['filter']) OR $vbulletin->GPC['filter'] == 0, 'checked="checked"', '') . ' />' . $vbphrase['phpkd_vbpts_filter_all'] . '</label><br />
			<label for="rb_filter_1"><input type="radio" name="filter" value="1" id="rb_filter_1" tabindex="1" ' . iif(isset($vbulletin->GPC['filter']) AND $vbulletin->GPC['filter'] == 1, 'checked="checked"', '') . ' />' . $vbphrase['phpkd_vbpts_filter_orphan'] . '</label><br />
			<label for="rb_filter_2"><input type="radio" name="filter" value="2" id="rb_filter_2" tabindex="1" ' . iif(isset($vbulletin->GPC['filter']) AND $vbulletin->GPC['filter'] == 2, 'checked="checked"', '') . ' />' . $vbphrase['phpkd_vbpts_filter_missing'] . '</label><br />
		</span>
	');
	print_yes_no_row($vbphrase['phpkd_vbpts_recentupdate'], 'recentupdate', $vbulletin->GPC['recentupdate']);
	print_submit_row($vbphrase['phpkd_vbpts_translate'], '_default_', 2, '', "\t<input type=\"button\" class=\"button\" value=\"" . $vbphrase['phpkd_vbpts_download'] . "\" tabindex=\"1\" onclick=\"window.location='phpkd_vbpts.php?do=files';\" />\n");

	echo '<script type="text/javascript">$(function(){$("#phrasetype").chained("#product");});</script>';
}


// #############################################################################

if ($_POST['do'] == 'download')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'filename'      => TYPE_STR,
		'just_phrases'  => TYPE_BOOL,
		'product'       => TYPE_STR,
		'custom'        => TYPE_BOOL,
		'charset'       => TYPE_NOHTML,
		'fixhtmlentity' => TYPE_BOOL,
		'dolanguageid' => TYPE_INT,
	));

	if (empty($vbulletin->GPC['filename']))
	{
		$vbulletin->GPC['filename'] = 'vbulletin-language.xml';
	}

	if (function_exists('set_time_limit') AND !SAFEMODE)
	{
		@set_time_limit(1200);
	}

	try
	{
		$doc = phpkd_vbpts_language_export_xml($vbulletin->GPC['dolanguageid'], $vbulletin->GPC['product'], $vbulletin->GPC['custom'], $vbulletin->GPC['just_phrases'], $vbulletin->GPC['charset'] ? $vbulletin->GPC['charset'] : 'ISO-8859-1');
	}
	catch (vB_Exception_AdminStopMessage $e)
	{
		// move print_stop_message calls from install_product so we
		// can use it places where said calls aren't appropriate.
		call_user_func_array('print_stop_message', $e->getParams());
	}

	require_once(DIR . '/includes/functions_file.php');
	file_download($doc, $vbulletin->GPC['filename'], 'text/xml');
}


// ##########################################################################

if ($_REQUEST['do'] == 'files')
{
	require_once(DIR . '/includes/functions_misc.php');
	$alllanguages = fetch_languages_array();
	$languages = array();
	$charsets = array(
		'UTF-8' => 'UTF-8',
	);
	$jscharsets = array(
			'-1' => 'UTF-8'
	);
	foreach ($alllanguages AS $languageid => $language)
	{
		$jscharsets[$languageid] = strtoupper($language['charset']);
		$languages[$languageid] = $language['title'];
		if ($languageid == $vbulletin->GPC['dolanguageid'])
		{
			$charset = strtoupper($language['charset']);
			if ($charset != 'UTF-8')
			{
				$charsets[$charset] = $charset;
			}
		}
	}
	?>
	<script type="text/javascript">
	<!--
	function js_set_charset(formobj, languageid)
	{
		var charsets = {
		<?php
		$output = '';
		foreach ($jscharsets AS $languageid => $charset)
		{
			$output .= "'$languageid' : '$charset',\r\n";
		}
		echo rtrim($output, "\r\n,") . "\r\n";
		?>
		};
		var charsetobj = formobj.charset;
		var charset = charsets[languageid];
		if (charset == charsetobj.options[0].value) // 'UTF-8' which is always in options[0]
		{	// Remove second charset item from list since this language is 'UTF-8'
			if (charsetobj.options.length == 2)
			{
				charsetobj.remove(1);
			}
		}
		else
		{
			if (charsetobj.options.length == 1)
			{	// Add an option!
				var option = document.createElement("option");
				charsetobj.add(option, null);
			}
			// Change the option, maybe to the same thing but that doesn't matter
			charsetobj.options[1].value = charset;
			charsetobj.options[1].text = charset;
		}
	}
	// -->
	</script>
	<?php

	// download form
	print_form_header('phpkd_vbpts', 'download', 0, 1, 'downloadform" target="download');
	print_table_header($vbphrase['download']);
	print_label_row($vbphrase['language'], '<select name="dolanguageid" tabindex="1" class="bginput" onchange="js_set_charset(this.form, this.value)">' . ($vbulletin->debug ? '<option value="-1">' . MASTER_LANGUAGE . '</option>' : '') . construct_select_options($languages, $vbulletin->GPC['dolanguageid']) . '</select>', '', 'top', 'languageid');
	print_select_row($vbphrase['product'], 'product', fetch_product_list());
	print_input_row($vbphrase['filename'], 'filename', 'vbulletin-language.xml');
	print_select_row($vbphrase['charset'], 'charset', $charsets);
	print_yes_no_row($vbphrase['include_custom_phrases'], 'custom', 0);
	print_yes_no_row($vbphrase['just_fetch_phrases'], 'just_phrases', 0);
	print_yes_no_row($vbphrase['phpkd_vbpts_fixhtmlentity'], 'fixhtmlentity', 1);
	print_submit_row($vbphrase['download']);
}


// #############################################################################

print_cp_footer();
?>