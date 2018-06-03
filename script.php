<?php
	date_default_timezone_set('Europe/Moscow');

	require 'nokogiri/nokogiri.php';

	function addFeedHeader($_to, $_link) {
		$_to = '<?xml version="1.0" encoding="UTF-8"?>';
		$_to .= '<rss xmlns:dc="http://purl.org/dc/elements/1.1/" version="2.0">';
		$_to .= '<channel>';
		$_to .= '<title>/gd/ feed</title>';
		$_to .= '<link>' . $_link . '/gd/</link>';
		$_to .= '<description>/gd/ feed</description>';

		return $_to;
	}

	/* https://stackoverflow.com/a/27516155 */
	$URLregEx = '/(http|https|ftp|ftps)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}(\/[^\s<]*)?/';

	$_STREAM_context =
		stream_context_create([
			'http' => [
				'header' => "User-Agent: Board2Feed script/1.0\r\n"
			]
		]);

	function generateFeedItem($_obj) {
		$_item = '<item>';

		$_title = empty($_obj['title']) ? 'Без названия' : htmlentities($_obj['title']);
		$_title = trim(preg_replace('/\s+/', ' ', $_title));

		$_item .= '<title><![CDATA[' . $_title . ']]></title>';
		$_item .= '<link>' . $_obj['link'] . '</link>';
		$_item .= '<pubDate>' . date("D, d M Y H:i:s O", $_obj['time']) . '</pubDate>';

		$_item .= '</item>';

		return $_item;
	}

	function sortThreadsByDate($_info) {
		usort($_info, function($a, $b) { return $a->timestamp - $b->timestamp; });
		$_info = array_reverse($_info);
		return $_info;
	}

	function sortThreadsByDate_N($_info) {
		usort($_info, function($a, $b) { return $a->opPost->date - $b->opPost->date; });
		$_info = array_reverse($_info);
		return $_info;
	}

	function sortThreadsByDate_F($_info) {
		usort($_info, function($a, $b) { return $a['data-time'] - $b['data-time']; });
		$_info = array_reverse($_info);
		return $_info;
	}

	function getDvach() {
		$dvachURL = 'https://2ch.hk';
		$apiURL = $dvachURL . '/gd/catalog_num.json';

		$rssFile = 'source.rss';

		if (filemtime($rssFile) < time() - 10) {
			$info = json_decode(file_get_contents($apiURL, false, $GLOBALS['_STREAM_context']));

			if ($info) {
				$threadsInfo = $info->threads;

				$threadsInfo = sortThreadsByDate($threadsInfo);

				$rssFeed = addFeedHeader($rssFeed, $dvachURL);

				foreach ($threadsInfo as &$thread) {
					$threadSubject = $thread->subject;
					$entity = html_entity_decode($threadSubject, ENT_NOQUOTES);

					if (preg_match($GLOBALS['URLregEx'], $entity)) { continue; }

					$rssFeed .= generateFeedItem([
						'title' =>  $threadSubject,
						'link' =>   $dvachURL . '/gd/res/' . $thread->num . '.html',
						'time' =>   $thread->timestamp
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
		$apiURL = $nullchURL . '/api/board?dir=gd';

		$rssFile = 'sourceN.rss';

		if (filemtime($rssFile) < time() - 10) {
			$info = json_decode(file_get_contents($apiURL, false, $GLOBALS['_STREAM_context']));

			if ($info !== false) {
				$threadsInfo = $info->threads;

				$threadsInfo = sortThreadsByDate_N($threadsInfo);

				$rssFeed = addFeedHeader($rssFeed, $nullchURL);

				foreach ($threadsInfo as &$thread) {
					$threadSubject = $thread->thread->title;
					$entity = html_entity_decode($threadSubject, ENT_NOQUOTES);

					if (preg_match($GLOBALS['URLregEx'], $entity)) { continue; }

					$rssFeed .= generateFeedItem([
						'title' =>  $threadSubject,
						'link' =>   $nullchURL . '/gd/' . $thread->thread->id,
						'time' =>   $thread->opPost->date
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
		$catalogURL = $foxURL . '/gd/catalog.html';

		$rssFile = 'sourceL.rss';

		if (filemtime($rssFile) < time() - 10) {
			$info = file_get_contents($catalogURL, false, $GLOBALS['_STREAM_context']);

			if ($info) {
				$html = new nokogiri($info);

				$threadsInfo = $html->get('.threads')->get('.mix')->toArray();

				$threadsInfo = sortThreadsByDate_F($threadsInfo);

				$rssFeed = addFeedHeader($rssFeed, $foxURL);

				foreach ($threadsInfo as &$thread) {
					$threadSubject = $thread['div'][0]['a'][0]['img'][0]['data-subject'];
					$entity = html_entity_decode($threadSubject, ENT_NOQUOTES);

					if (preg_match($GLOBALS['URLregEx'], $entity)) { continue; }

					$rssFeed .= generateFeedItem([
						'title' =>  $threadSubject,
						'link' =>   $foxURL . '/gd/res/' . $thread['data-id'] . '.html',
						'time' =>   $thread['data-time']
					]);
				}

				$rssFeed .= '</channel>';
				$rssFeed .= '</rss>';

				file_put_contents(dirname(__FILE__) . '/' . $rssFile, $rssFeed);
			}
		}
	}

	getDvach();
	getNullch();
	getFox();
?>
<!DOCTYPE html><html><body style="text-align: center"><a href="https://github.com/tehcojam/board2feed">sauce</a></body></html>
