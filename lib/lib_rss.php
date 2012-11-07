<?php
// vérifie qu'on est connecté
function is_logged () {
	return Session::param ('mail') != false;
}

// vérifie que le système d'authentification est configuré
function login_is_conf ($conf) {
	return $conf->mailLogin () != false;
}

// tiré de Shaarli de Seb Sauvage
function small_hash ($txt) {
	$t = rtrim (base64_encode (hash ('crc32', $txt, true)), '=');
	$t = str_replace ('+', '-', $t); // Get rid of characters which need encoding in URLs.
	$t = str_replace ('/', '_', $t);
	$t = str_replace ('=', '@', $t);

	return $t;
}

function timestamptodate ($t, $hour = true) {
	$jour = date ('d', $t);
	$mois = date ('m', $t);
	$annee = date ('Y', $t);
	
	switch ($mois) {
	case 1:
		$mois = 'janvier';
		break;
	case 2:
		$mois = 'février';
		break;
	case 3:
		$mois = 'mars';
		break;
	case 4:
		$mois = 'avril';
		break;
	case 5:
		$mois = 'mai';
		break;
	case 6:
		$mois = 'juin';
		break;
	case 7:
		$mois = 'juillet';
		break;
	case 8:
		$mois = 'août';
		break;
	case 9:
		$mois = 'septembre';
		break;
	case 10:
		$mois = 'octobre';
		break;
	case 11:
		$mois = 'novembre';
		break;
	case 12:
		$mois = 'décembre';
		break;
	}
	
	$date = $jour . ' ' . $mois . ' ' . $annee;
	if ($hour) {
		return $date . date (' \à H\:i', $t);
	} else {
		return $date;
	}
}

function sortEntriesByDate ($entry1, $entry2) {
	return $entry2->date (true) - $entry1->date (true);
}
function sortReverseEntriesByDate ($entry1, $entry2) {
	return $entry1->date (true) - $entry2->date (true);
}

function get_domain ($url) {
	return parse_url($url, PHP_URL_HOST);
}

function opml_export ($cats) {
	$txt = '';
	
	foreach ($cats as $cat) {
		$txt .= '<outline text="' . $cat['name'] . '">' . "\n";
		
		foreach ($cat['feeds'] as $feed) {
			$txt .= "\t" . '<outline text="' . cleanText ($feed->name ()) . '" type="rss" xmlUrl="' . $feed->url () . '" htmlUrl="' . $feed->website () . '" />' . "\n";
		}
		
		$txt .= '</outline>' . "\n";
	}
	
	return $txt;
}

function cleanText ($text) {
	return preg_replace ('/&[\w]+;/', '', $text);
}

function opml_import ($xml) {
	$opml = @simplexml_load_string ($xml);

	if (!$opml) {
		return array (array (), array ());
	}

	$categories = array ();
	$feeds = array ();

	foreach ($opml->body->outline as $outline) {
		if (!isset ($outline['xmlUrl'])) {
			// Catégorie
			$title = '';
			
			if (isset ($outline['text'])) {
				$title = (string) $outline['text'];
			} elseif (isset ($outline['title'])) {
				$title = (string) $outline['title'];
			}
			
			if ($title) {
				$cat = new Category ($title);
				$categories[] = $cat;
				
				$feeds = array_merge ($feeds, getFeedsOutline ($outline, $cat->id ()));
			}
		} else {
			// Flux rss
			$feeds[] = getFeed ($outline, '');
		}
	}

	return array ($categories, $feeds);
}

/**
 * import all feeds of a given outline tag
 */
function getFeedsOutline ($outline, $cat_id) {
	$feeds = array ();
	
	foreach ($outline->children () as $child) {
		if (isset ($child['xmlUrl'])) {
			$feeds[] = getFeed ($child, $cat_id);
		} else {
			$feeds = array_merge(
				$feeds,
				getFeedsOutline ($child, $cat_id)
			);
		}
	}
	
	return $feeds;
}

function getFeed ($outline, $cat_id) {
	$url = (string) $outline['xmlUrl'];
	$feed = new Feed ($url);
	$feed->_category ($cat_id);

	return $feed;
}

/*
 * Vérifie pour un site donné s'il faut aller parser directement sur le site
 * Renvoie le path (id et class html) pour récupérer le contenu, false si pas besoin
 * On se base sur une base connue de sites
 */
function get_path ($url) {
	$list_sites_parse = include (PUBLIC_PATH . '/data/Sites.array.php');
	if (isset ($list_sites_parse[$url])) {
		return $list_sites_parse[$url];
	} else {
		return false;
	}
}


/* supprime les trucs inutiles des balises html */
function good_bye_extra ($element) {
	$element->style = null;
	$element->class = null;
	$element->id = null;
	$element->onload = null;
}
/* permet de récupérer le contenu d'un article pour un flux qui n'est pas complet */
function get_content_by_parsing ($url, $path) {
	$html = new simple_html_dom ();
	$html->set_callback ('good_bye_extra');
	$ok = $html->load_file ($url);
	
	if ($ok !== false) {
		$content = $html->find ($path, 0);
		$html->clear ();
		
		if ($content) {
			return $content->__toString ();
		} else {
			throw new Exception ();
		}
	} else {
		throw new Exception ();
	}
}
