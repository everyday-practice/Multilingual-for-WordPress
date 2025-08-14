<?php

/**
 * Plugin Name: Multilingual.js for WordPress
 * Description: 오픈소스 라이브러리 multilingual.js를 워드프레스에 맞게 재구현하여 서버사이드 래핑으로 FOUC를 최소화, 첫 렌더부터 일관된 타이포그래피를 제공합니다.
 * Version: 1.1.0
 * Author: Everyday Practice
 * Plugin URI: https://github.com/everyday-practice/Multilingual.js-for-WordPress
 * Author URI: https://everyday-practice.com
 */
if (!defined('ABSPATH'))
	exit;

/**
 * Add a Settings link on the Plugins page row
 */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
	$url = admin_url('options-general.php?page=mlwp-settings');
	$settings_link = '<a href="' . esc_url($url) . '">Settings</a>';
	array_unshift($links, $settings_link);
	return $links;
});

/**
 * 설정: 동작 옵션
 * - types: 어떤 유형을 감쌀지 선택 (en, ko, cn, jp, num, punct)
 * - class_prefix: 기본 접두사 (ml-)
 * - auto_selectors: 자동 적용할 CSS 선택자들
 * - custom_charsets: 사용자 정의 문자세트
 */

function mlwp_get_config()
{
	$defaults = [
		'types' => ['en', 'num', 'punct'],
		'class_prefix' => 'ml',
		'auto_selectors' => [],
		'exclude_selectors' => [],
		'shortcode_whitelist' => [],
		'custom_charsets' => [],
	];

	$opts = get_option('mlwp_options', []);

	$allowed_types = ['en', 'ko', 'cn', 'jp', 'num', 'punct'];
	$types = isset($opts['types']) ? array_values(array_intersect($allowed_types, (array) $opts['types'])) : $defaults['types'];
	if (empty($types))
		$types = $defaults['types'];

	$class_prefix = isset($opts['class_prefix']) && $opts['class_prefix'] !== '' ? preg_replace('/[^a-z0-9_\-]/i', '', $opts['class_prefix']) : $defaults['class_prefix'];

	$auto_selectors = isset($opts['auto_selectors']) && is_array($opts['auto_selectors'])
		? array_values(array_filter(array_map('trim', $opts['auto_selectors'])))
		: $defaults['auto_selectors'];

	$exclude_selectors = isset($opts['exclude_selectors']) && is_array($opts['exclude_selectors'])
		? array_values(array_filter(array_map('trim', $opts['exclude_selectors'])))
		: $defaults['exclude_selectors'];

	$shortcode_whitelist = isset($opts['shortcode_whitelist']) && is_array($opts['shortcode_whitelist'])
		? array_values(array_filter(array_map('trim', $opts['shortcode_whitelist'])))
		: $defaults['shortcode_whitelist'];

	$custom_charsets = isset($opts['custom_charsets']) && is_array($opts['custom_charsets']) ? $opts['custom_charsets'] : $defaults['custom_charsets'];

	return [
		'types' => $types,
		'class_prefix' => $class_prefix,
		'auto_selectors' => $auto_selectors,
		'exclude_selectors' => $exclude_selectors,
		'shortcode_whitelist' => $shortcode_whitelist,
		'custom_charsets' => $custom_charsets,
	];
}

/**
 * 프론트엔드에서 분리된 JS 로드 및 설정 주입
 */
function mlwp_enqueue_scripts()
{
	if (is_admin()) {
		return;
	}

	$handle = 'mlwp';
	$src = plugin_dir_url(__FILE__) . 'multilingual-js-for-wordpress.js';
	wp_enqueue_script($handle, $src, array(), '1.1.0', true);

	$cfg = mlwp_get_config();
	$inline = 'window.MLWP_CFG = ' . wp_json_encode([
		'types' => $cfg['types'],
		'classPrefix' => $cfg['class_prefix'],
		'selectors' => $cfg['auto_selectors'],
		'excludeSelectors' => isset($cfg['exclude_selectors']) ? $cfg['exclude_selectors'] : [],
	], JSON_UNESCAPED_UNICODE) . ';';
	wp_add_inline_script($handle, $inline, 'before');
}

/**
 * Server-side wrapping utilities
 */
function mlwp_get_patterns()
{
	return [
		'en' => '[A-Za-z]+',
		'ko' => '[\x{3131}-\x{318E}\x{AC00}-\x{D7A3}]+',
		'cn' => '[\x{4E00}-\x{9FBF}]+',
		'jp' => '[\x{3040}-\x{309F}\x{30A0}-\x{30FF}]+',
		'num' => '[0-9]+',
		'punct' => '[（）().#^\-&,;:@%*，、。」\'"‘’“”«»–—…]+',
	];
}

function mlwp_build_combined_regex(array $types)
{
	$all = mlwp_get_patterns();
	$parts = [];
	foreach ($types as $t) {
		if (isset($all[$t])) {
			$parts[] = '(' . $all[$t] . ')';
		}
	}
	if (empty($parts))
		return null;
	return '/' . implode('|', $parts) . '/u';
}

function mlwp_is_excluded_tag($tagName)
{
	$tagName = strtolower($tagName);
	return in_array($tagName, ['script', 'style', 'code', 'pre', 'textarea', 'kbd', 'samp'], true);
}

function mlwp_parent_is_wrapped_span($node, $classPrefix)
{
	$parent = $node->parentNode;
	if (!$parent || $parent->nodeType !== XML_ELEMENT_NODE)
		return false;
	if (strtolower($parent->tagName) !== 'span')
		return false;
	$cls = $parent->getAttribute('class');
	if (!$cls)
		return false;
	return preg_match('/\b' . preg_quote($classPrefix, '/') . '\-[a-z]+\b/i', $cls) === 1;
}

function mlwp_split_protected_bracket_segments($text)
{
	$segments = [];
	$length = mb_strlen($text, 'UTF-8');
	$cursor = 0;
	while (true) {
		$start = mb_strpos($text, '[', $cursor, 'UTF-8');
		if ($start === false)
			break;
		$end = mb_strpos($text, ']', $start + 1, 'UTF-8');
		if ($end === false)
			break;
		if ($start > $cursor) {
			$segments[] = ['text' => mb_substr($text, $cursor, $start - $cursor, 'UTF-8'), 'protected' => false];
		}
		$segments[] = ['text' => mb_substr($text, $start, $end - $start + 1, 'UTF-8'), 'protected' => true];
		$cursor = $end + 1;
	}
	if ($cursor < $length) {
		$segments[] = ['text' => mb_substr($text, $cursor, $length - $cursor, 'UTF-8'), 'protected' => false];
	}
	if (empty($segments)) {
		$segments[] = ['text' => $text, 'protected' => false];
	}
	return $segments;
}

function mlwp_wrap_text_node(DOMDocument $doc, DOMText $textNode, $regex, array $types, $classPrefix)
{
	$subject = $textNode->nodeValue;
	if ($subject === '' || $regex === null)
		return false;

	// 1) 대괄호 보호 세그먼트로 분리
	$segments = mlwp_split_protected_bracket_segments($subject);

	// 2) 보호 영역을 제외한 세그먼트에 실제 매칭이 있는지 먼저 확인 (없으면 변경하지 않음)
	$hasMatch = false;
	foreach ($segments as $seg) {
		if (!empty($seg['protected']))
			continue;
		if ($seg['text'] !== '' && preg_match($regex, $seg['text'])) {
			$hasMatch = true;
			break;
		}
	}
	if (!$hasMatch)
		return false;

	// 3) 변경 수행: 보호 세그먼트는 그대로, 일반 세그먼트는 기존 래핑 로직 적용
	$fragment = $doc->createDocumentFragment();

	foreach ($segments as $seg) {
		$segText = $seg['text'];
		if (!empty($seg['protected'])) {
			if ($segText !== '')
				$fragment->appendChild($doc->createTextNode($segText));
			continue;
		}

		$matches = [];
		if (!preg_match_all($regex, $segText, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
			if ($segText !== '')
				$fragment->appendChild($doc->createTextNode($segText));
			continue;
		}

		$cursor = 0;
		foreach ($matches as $m) {
			$full = $m[0][0];
			$offsetBytes = $m[0][1];
			$lenBytes = strlen($full);
			if ($offsetBytes > $cursor) {
				$plain = mb_strcut($segText, $cursor, $offsetBytes - $cursor, 'UTF-8');
				if ($plain !== '')
					$fragment->appendChild($doc->createTextNode($plain));
			}
			$matchedType = null;
			for ($i = 1; $i <= count($types); $i++) {
				if (isset($m[$i]) && $m[$i][0] !== '' && $m[$i][0] !== null) {
					$matchedType = $types[$i - 1];
					break;
				}
			}
			if ($matchedType === null) {
				$fragment->appendChild($doc->createTextNode($full));
			} else {
				$span = $doc->createElement('span');
				$span->setAttribute('class', $classPrefix . '-' . $matchedType);
				$span->appendChild($doc->createTextNode($full));
				$fragment->appendChild($span);
			}
			$cursor = $offsetBytes + $lenBytes;
		}
		$rest = mb_strcut($segText, $cursor, null, 'UTF-8');
		if ($rest !== '')
			$fragment->appendChild($doc->createTextNode($rest));
	}

	$parent = $textNode->parentNode;
	$parent->insertBefore($fragment, $textNode);
	$parent->removeChild($textNode);
	return true;
}

function mlwp_selector_to_xpath($selector)
{
	$selector = trim($selector);
	if ($selector === '')
		return null;
	if ($selector[0] === '.') {
		$cls = substr($selector, 1);
		return '//*[contains(concat(" ", normalize-space(@class), " "), " ' . $cls . ' ")]';
	}
	$tokens = preg_split('/\s+/', $selector);
	$xpath = '';
	foreach ($tokens as $tok) {
		$tok = trim($tok);
		if ($tok === '')
			continue;
		$parts = explode('.', $tok);
		$tag = array_shift($parts);
		if ($tag === '')
			$tag = '*';
		$conds = [];
		foreach ($parts as $cls) {
			$conds[] = 'contains(concat(" ", normalize-space(@class), " "), " ' . $cls . ' ")';
		}
		$step = '//' . $tag;
		if (!empty($conds)) {
			$step .= '[' . implode(' and ', $conds) . ']';
		}
		$xpath .= $step;
	}
	return $xpath ?: null;
}

function mlwp_extract_class_tokens_from_selectors(array $selectors)
{
	$list = [];
	foreach ($selectors as $sel) {
		if (preg_match_all('/\.([A-Za-z0-9_\-]+)/', $sel, $m)) {
			foreach ($m[1] as $cls)
				$list[$cls] = true;
		}
	}
	return array_keys($list);
}

function mlwp_wrap_html_inside_selectors($html, array $selectors, array $types, $classPrefix, array $excludeSelectors = [])
{
	if (trim($html) === '' || empty($selectors) || empty($types))
		return $html;
	libxml_use_internal_errors(true);
	$doc = new DOMDocument('1.0', 'UTF-8');
	$wrapper = '<div id="ml-root">' . $html . '</div>';
	$doc->loadHTML('<?xml encoding="UTF-8"?>' . $wrapper, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
	$xpath = new DOMXPath($doc);
	$targetNodes = [];
	foreach ($selectors as $sel) {
		$x = mlwp_selector_to_xpath($sel);
		if (!$x)
			continue;
		$nodes = $xpath->query($x);
		if (!$nodes)
			continue;
		foreach ($nodes as $node) {
			$targetNodes[spl_object_hash($node)] = $node;
		}
	}
	if (empty($targetNodes)) {
		return $html;
	}
	$regex = mlwp_build_combined_regex($types);
	foreach ($targetNodes as $el) {
		// Build exclude set per root, using full selector and rightmost simple token as fallback
		$excludeNodeSet = [];
		if (!empty($excludeSelectors)) {
			foreach ($excludeSelectors as $exSel) {
				$exSel = trim($exSel);
				if ($exSel === '') continue;
				$ex = mlwp_selector_to_xpath($exSel);
				if ($ex) {
					$exNodes = $xpath->query($ex, $el);
					if ($exNodes) {
						foreach ($exNodes as $exNode) {
							$excludeNodeSet[spl_object_hash($exNode)] = $exNode;
						}
					}
				}
				// rightmost token fallback (handles cases where ancestor is outside the snippet)
				$parts = preg_split('/\s+/', $exSel);
				$tail = is_array($parts) ? trim(end($parts)) : '';
				if ($tail && $tail !== $exSel) {
					$exTail = mlwp_selector_to_xpath($tail);
					if ($exTail) {
						$exTailNodes = $xpath->query($exTail, $el);
						if ($exTailNodes) {
							foreach ($exTailNodes as $exNode) {
								$excludeNodeSet[spl_object_hash($exNode)] = $exNode;
							}
						}
					}
				}
			}
		}
		$stack = [$el];
		while ($stack) {
			$node = array_pop($stack);
			if ($node->nodeType === XML_ELEMENT_NODE) {
				if (mlwp_is_excluded_tag($node->tagName))
					continue;
				// If this node is within any excluded ancestor, skip its subtree
				$skip = false;
				if (!empty($excludeNodeSet)) {
					$ancestor = $node;
					while ($ancestor && $ancestor->nodeType === XML_ELEMENT_NODE) {
						if (isset($excludeNodeSet[spl_object_hash($ancestor)])) { $skip = true; break; }
						$ancestor = $ancestor->parentNode;
					}
				}
				if ($skip) continue;
				$children = [];
				for ($i = 0; $i < $node->childNodes->length; $i++) {
					$children[] = $node->childNodes->item($i);
				}
				foreach (array_reverse($children) as $child) {
					$stack[] = $child;
				}
			} elseif ($node->nodeType === XML_TEXT_NODE) {
				if (mlwp_parent_is_wrapped_span($node, $classPrefix))
					continue;
				mlwp_wrap_text_node($doc, $node, $regex, $types, $classPrefix);
			}
		}
	}
	$root = $doc->getElementById('ml-root');
	if (!$root)
		return $html;
	$out = '';
	foreach ($root->childNodes as $child) {
		$out .= $doc->saveHTML($child);
	}
	return $out;
}

function mlwp_server_wrap_content($html)
{
	$cfg = mlwp_get_config();
	$selectors = $cfg['auto_selectors'];
	$types = $cfg['types'];
	$classPrefix = $cfg['class_prefix'];
	$excludes = isset($cfg['exclude_selectors']) ? (array) $cfg['exclude_selectors'] : [];
	if (!$html || empty($selectors) || empty($types))
		return $html;
	$needles = mlwp_extract_class_tokens_from_selectors($selectors);
	$found = false;
	foreach ($needles as $cls) {
		if (strpos($html, $cls) !== false) {
			$found = true;
			break;
		}
	}
	if (!$found)
		return $html;
	return mlwp_wrap_html_inside_selectors($html, $selectors, $types, $classPrefix, $excludes);
}

function mlwp_server_wrap_shortcode_output($html)
{
	$cfg = mlwp_get_config();
	$types = $cfg['types'];
	$classPrefix = $cfg['class_prefix'];
	$excludes = isset($cfg['exclude_selectors']) ? (array) $cfg['exclude_selectors'] : [];
	if (!$html || empty($types))
		return $html;
	$fallback = ['p', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'figcaption', 'td', 'th', 'a', 'span', 'em', 'strong', 'div'];
	return mlwp_wrap_html_inside_selectors($html, $fallback, $types, $classPrefix, $excludes);
}

// Filters: blocks, shortcodes, content, and menus
add_filter('render_block', function ($block_content, $block) {
	if (empty($block_content))
		return $block_content;
	return mlwp_server_wrap_content($block_content);
}, 20, 2);

add_filter('do_shortcode_tag', function ($output, $tag, $attr) {
	if (empty($output))
		return $output;
	$cfg = mlwp_get_config();
	$whitelist = isset($cfg['shortcode_whitelist']) ? (array) $cfg['shortcode_whitelist'] : [];
	if (empty($whitelist) || !in_array($tag, $whitelist, true)) {
		return $output;
	}
	return mlwp_server_wrap_shortcode_output($output);
}, 20, 3);

add_filter('the_content', function ($content) {
	if (is_admin())
		return $content;
	if (empty($content))
		return $content;
	return mlwp_server_wrap_content($content);
}, 12);

/**
 * 기본 훅 등록 (항상 실행)
 */
function mlwp_register_hooks()
{
	// 분리된 JS 로드 및 설정 주입
	add_action('wp_enqueue_scripts', 'mlwp_enqueue_scripts', 20);
}

// 기본 훅 등록 (항상 실행)
add_action('init', 'mlwp_register_hooks');
if (is_admin())
	require_once __DIR__ . '/admin/settings.php';

/**
 * Generic: Intercept admin-ajax.php output and REST API responses
 * to ensure server-side wrapping also applies to AJAX/REST driven updates.
 */
// 1) admin-ajax.php: output buffering
add_action('init', function () {
	if (!wp_doing_ajax()) {
		return;
	}
	ob_start(function ($buffer) {
		if (!function_exists('mlwp_server_wrap_content')) {
			return $buffer;
		}
		// Try JSON first
		$decoded = json_decode($buffer, true);
		if (is_array($decoded)) {
			$keys = ['template', 'html', 'content', 'rendered', 'markup', 'output'];
			$walker = function (&$node) use (&$walker, $keys) {
				if (is_array($node)) {
					foreach ($node as $k => &$v) {
						if (is_string($v) && (in_array($k, $keys, true) || strpos($v, '<') !== false)) {
							$v = mlwp_server_wrap_content($v);
						} else {
							$walker($v);
						}
					}
				}
			};
			$walker($decoded);
			return wp_json_encode($decoded, JSON_UNESCAPED_UNICODE);
		}
		// Fallback: plain HTML
		if (is_string($buffer) && strpos($buffer, '<') !== false) {
			return mlwp_server_wrap_content($buffer);
		}
		return $buffer;
	});
}, 0);

// 2) REST API: filter dispatched response
add_filter('rest_post_dispatch', function ($result, $server, $request) {
	if (!function_exists('mlwp_server_wrap_content')) {
		return $result;
	}
	if (!($result instanceof WP_REST_Response)) {
		return $result;
	}
	$data = $result->get_data();
	$keys = ['template', 'html', 'content', 'excerpt', 'rendered', 'markup', 'output'];
	$walker = function (&$node) use (&$walker, $keys) {
		if (is_array($node)) {
			foreach ($node as $k => &$v) {
				if (is_string($v) && (in_array($k, $keys, true) || strpos($v, '<') !== false)) {
					$v = mlwp_server_wrap_content($v);
				} else {
					$walker($v);
				}
			}
		}
	};
	$walker($data);
	$result->set_data($data);
	return $result;
}, 20, 3);
