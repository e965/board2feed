<?php
	date_default_timezone_set('Europe/Moscow');

	require 'nokogiri/nokogiri.php';

	function addFeedHeader($_link, $_feedTitle, $_board = 'gd') {
		$_feed =   '<?xml version="1.0" encoding="UTF-8"?>';
		$_feed .=  '<rss xmlns:dc="http://purl.org/dc/elements/1.1/" version="2.0">';
		$_feed .=  '<channel>';
		$_feed .=  '<title>'. $_feedTitle .'</title>';
		$_feed .=  '<link>' . $_link . '</link>';
		$_feed .=  '<description>/' . $_board . '/ feed</description>';

		return $_feed;
	}

	function addFeedFooter() {
		$_feed =   '</channel>';
		$_feed .=  '</rss>';

		return $_feed;
	}

	/* https://stackoverflow.com/a/27516155 */
	$URLregEx = '/(http|https|ftp|ftps)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}(\/[^\s<]*)?/';

	$_STREAM_context =
		stream_context_create([
			'http' => [
				'header' => "User-Agent: Board2Feed script/1.2 beta\r\n"
			]
		]);

	$globalFeed = [];

	function generateFeedItem($_obj, $_isGlobal = false) {
		$_item = '<item>';

		if (!$_isGlobal) { array_push($GLOBALS['globalFeed'], $_obj); }

		$_title = empty($_obj['title']) ? 'Без названия' : htmlentities($_obj['title']);
		$_title = trim(preg_replace('/\s+/', ' ', $_title));

		$_item .= '<title><![CDATA[' . $_title . ']]></title>';
		$_item .= '<link>' . $_obj['link'] . '</link>';
		$_item .= '<pubDate>' . date("D, d M Y H:i:s O", $_obj['timestamp']) . '</pubDate>';

		$_item .= '</item>';

		return $_item;
	}

	function sortThreadsByDate($_info, $_type) {
		usort($_info, function($a, $b) use ($_type) {
			switch($_type) {
				case 'global':
					return strcmp($a['timestamp'], $b['timestamp']); break;
				case '2ch':
					return strcmp($a->timestamp, $b->timestamp); break;
				case '0ch':
					return strcmp($a->opPost->date, $b->opPost->date); break;
				case 'fox':
					return strcmp(intval($a['data-time']), intval($b['data-time'])); break;
			};
		});

		$_info = array_reverse($_info);

		return $_info;
	}

	function getDvach() {
		$dvachURL = 'https://2ch.hk';

		$board = 'gd';
		$boardURL = $dvachURL . '/' . $board;

		$apiURL = $boardURL . '/catalog_num.json';

		$xmlFile = 'source.rss';

		global $URLregEx, $_STREAM_context;

		if (filemtime($xmlFile) < time() - 10) {
			$info = json_decode(file_get_contents($apiURL, false, $_STREAM_context));

			if ($info) {
				$threadsInfo = $info->threads;

				$threadsInfo = sortThreadsByDate($threadsInfo, '2ch');

				$_feed = addFeedHeader($boardURL, '2ch/' . $board . '/ feed');

				foreach ($threadsInfo as &$thread) {
					$threadSubject = $thread->subject;
					$entity = html_entity_decode($threadSubject, ENT_NOQUOTES);

					if (preg_match($URLregEx, $entity)) { continue; }

					$_feed .= generateFeedItem([
						'title' =>      $threadSubject,
						'link' =>       $boardURL . '/res/' . $thread->num . '.html',
						'timestamp' =>  $thread->timestamp
					]);
				}

				$_feed .= addFeedFooter();

				file_put_contents(dirname(__FILE__) . '/' . $xmlFile, $_feed);
			}
		}
	}

	function getNullch() {
		$nullchURL = 'https://0chan.xyz';

		$board = 'gd';
		$boardURL = $nullchURL . '/' . $board;

		$apiURL = $nullchURL . '/api/board?dir=' . $board;

		$xmlFile = 'sourceN.rss';

		global $URLregEx, $_STREAM_context;

		if (filemtime($xmlFile) < time() - 10) {
			$info = json_decode(file_get_contents($apiURL, false, $_STREAM_context));

			if ($info !== false) {
				$threadsInfo = $info->threads;

				$threadsInfo = sortThreadsByDate($threadsInfo, '0ch');

				$_feed = addFeedHeader($boardURL, '0ch/' . $board . '/ feed');

				foreach ($threadsInfo as &$thread) {
					$threadSubject = $thread->thread->title;
					$entity = html_entity_decode($threadSubject, ENT_NOQUOTES);

					if (preg_match($URLregEx, $entity)) { continue; }

					$_feed .= generateFeedItem([
						'title' =>      $threadSubject,
						'link' =>       $boardURL . '/' . $thread->thread->id,
						'timestamp' =>  $thread->opPost->date
					]);
				}

				$_feed .= addFeedFooter();

				file_put_contents(dirname(__FILE__) . '/' . $xmlFile, $_feed);
			}
		}
	}

	function getFox() {
		$foxURL = 'https://lolifox.org';

		$board = 'gd';
		$boardURL = $foxURL . '/' . $board;

		$catalogURL = $boardURL . '/catalog.html';

		$xmlFile = 'sourceF.rss';

		global $URLregEx, $_STREAM_context;

		if (filemtime($xmlFile) < time() - 10) {
			$info = file_get_contents($catalogURL, false, $_STREAM_context);

			if ($info) {
				$html = new nokogiri($info);

				$threadsInfo = $html->get('.threads')->get('.mix')->toArray();

				$threadsInfo = sortThreadsByDate($threadsInfo, 'fox');

				$_feed = addFeedHeader($boardURL, 'lolifox/' . $board . '/ feed');

				foreach ($threadsInfo as &$thread) {
					$threadSubject = $thread['div'][0]['a'][0]['img'][0]['data-subject'];
					$entity = html_entity_decode($threadSubject, ENT_NOQUOTES);

					if (preg_match($URLregEx, $entity)) { continue; }

					$_feed .= generateFeedItem([
						'title' =>      $threadSubject,
						'link' =>       $boardURL . '/res/' . $thread['data-id'] . '.html',
						'timestamp' =>  intval($thread['data-time'])
					]);
				}

				$_feed .= addFeedFooter();

				file_put_contents(dirname(__FILE__) . '/' . $xmlFile, $_feed);
			}
		}
	}

	function createGlobalFeed() {
		$xmlFile = 'sourceGlobal.rss';

		$board = 'gd';

		if (filemtime($xmlFile) < time() - 10) {
			$feedData = $GLOBALS['globalFeed'];

			$feedData = sortThreadsByDate($feedData, 'global');

			$_feed = addFeedHeader('https://github.com/tehcojam/board2feed', 'Global /' . $board . '/ feed');

			foreach ($feedData as &$feedItem) {
				$_feed .= generateFeedItem($feedItem, true);
			}

			$_feed .= addFeedFooter();

			file_put_contents(dirname(__FILE__) . '/' . $xmlFile, $_feed);
		}
	}

	getDvach('');
	getNullch();
	getFox();

	createGlobalFeed();
?>
<!DOCTYPE html><html><body style="text-align: center"><a href="https://github.com/tehcojam/board2feed">sauce</a></body></html>
