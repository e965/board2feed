<?php
	date_default_timezone_set('Europe/Moscow');

	require 'nokogiri/nokogiri.php';

	function addFeedHeader($_to, $_link, $_board = 'gd') {
		$_to =   '<?xml version="1.0" encoding="UTF-8"?>';
		$_to .=  '<rss xmlns:dc="http://purl.org/dc/elements/1.1/" version="2.0">';
		$_to .=  '<channel>';
		$_to .=  '<title>/' . $_board . '/ feed</title>';
		$_to .=  '<link>' . $_link . '</link>';
		$_to .=  '<description>/gd/ feed</description>';

		return $_to;
	}

	/* https://stackoverflow.com/a/27516155 */
	$URLregEx = '/(http|https|ftp|ftps)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}(\/[^\s<]*)?/';

	$_STREAM_context =
		stream_context_create([
			'http' => [
				'header' => "User-Agent: Board2Feed script/1.1\r\n"
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

		$rssFile = 'source.rss';

		global $URLregEx, $_STREAM_context;

		if (filemtime($rssFile) < time() - 10) {
			$info = json_decode(file_get_contents($apiURL, false, $_STREAM_context));

			if ($info) {
				$threadsInfo = $info->threads;

				$threadsInfo = sortThreadsByDate($threadsInfo, '2ch');

				$rssFeed = addFeedHeader($rssFeed, $boardURL);

				foreach ($threadsInfo as &$thread) {
					$threadSubject = $thread->subject;
					$entity = html_entity_decode($threadSubject, ENT_NOQUOTES);

					if (preg_match($URLregEx, $entity)) { continue; }

					$rssFeed .= generateFeedItem([
						'title' =>      $threadSubject,
						'link' =>       $boardURL . '/res/' . $thread->num . '.html',
						'timestamp' =>  $thread->timestamp
					]);
				}

				$rssFeed .= '</channel>';
				$rssFeed .= '</rss>';

				file_put_contents(dirname(__FILE__) . '/' . $rssFile, $rssFeed);
			}
		}
	}

	function getNullch() {
		$nullchURL = 'https://0chan.xyz';

		$board = 'gd';
		$boardURL = $nullchURL . '/' . $board;

		$apiURL = $nullchURL . '/api/board?dir=' . $board;

		$rssFile = 'sourceN.rss';

		global $URLregEx, $_STREAM_context;

		if (filemtime($rssFile) < time() - 10) {
			$info = json_decode(file_get_contents($apiURL, false, $_STREAM_context));

			if ($info !== false) {
				$threadsInfo = $info->threads;

				$threadsInfo = sortThreadsByDate($threadsInfo, '0ch');

				$rssFeed = addFeedHeader($rssFeed, $boardURL);

				foreach ($threadsInfo as &$thread) {
					$threadSubject = $thread->thread->title;
					$entity = html_entity_decode($threadSubject, ENT_NOQUOTES);

					if (preg_match($URLregEx, $entity)) { continue; }

					$rssFeed .= generateFeedItem([
						'title' =>      $threadSubject,
						'link' =>       $boardURL . '/' . $thread->thread->id,
						'timestamp' =>  $thread->opPost->date
					]);
				}

				$rssFeed .= '</channel>';
				$rssFeed .= '</rss>';

				file_put_contents(dirname(__FILE__) . '/' . $rssFile, $rssFeed);
			}
		}
	}

	function getFox() {
		$foxURL = 'https://lolifox.org';

		$board = 'gd';
		$boardURL = $foxURL . '/' . $board;

		$catalogURL = $boardURL . '/catalog.html';

		$rssFile = 'sourceF.rss';

		global $URLregEx, $_STREAM_context;

		if (filemtime($rssFile) < time() - 10) {
			$info = file_get_contents($catalogURL, false, $_STREAM_context);

			if ($info) {
				$html = new nokogiri($info);

				$threadsInfo = $html->get('.threads')->get('.mix')->toArray();

				$threadsInfo = sortThreadsByDate($threadsInfo, 'fox');

				$rssFeed = addFeedHeader($rssFeed, $boardURL);

				foreach ($threadsInfo as &$thread) {
					$threadSubject = $thread['div'][0]['a'][0]['img'][0]['data-subject'];
					$entity = html_entity_decode($threadSubject, ENT_NOQUOTES);

					if (preg_match($URLregEx, $entity)) { continue; }

					$rssFeed .= generateFeedItem([
						'title' =>      $threadSubject,
						'link' =>       $boardURL . '/res/' . $thread['data-id'] . '.html',
						'timestamp' =>  intval($thread['data-time'])
					]);
				}

				$rssFeed .= '</channel>';
				$rssFeed .= '</rss>';

				file_put_contents(dirname(__FILE__) . '/' . $rssFile, $rssFeed);
			}
		}
	}

	function createGlobalFeed() {
		$rssFile = 'sourceGlobal.rss';

		if (filemtime($rssFile) < time() - 10) {
			$feedData = $GLOBALS['globalFeed'];

			$feedData = sortThreadsByDate($feedData, 'global');

			$rssFeed = addFeedHeader($rssFeed, 'https://github.com/tehcojam/board2feed');

			foreach ($feedData as &$feedItem) {
				$rssFeed .= generateFeedItem($feedItem, true);
			}

			$rssFeed .= '</channel>';
			$rssFeed .= '</rss>';

			file_put_contents(dirname(__FILE__) . '/' . $rssFile, $rssFeed);
		}
	}

	getDvach('');
	getNullch();
	getFox();

	createGlobalFeed();
?>
<!DOCTYPE html><html><body style="text-align: center"><a href="https://github.com/tehcojam/board2feed">sauce</a></body></html>
