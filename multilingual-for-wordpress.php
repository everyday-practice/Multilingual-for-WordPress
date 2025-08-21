<?php

/**
 * Plugin Name: Multilingual for WordPress
 * Description: Server-side text wrapping for consistent typography across languages. Wraps text in spans by language type to eliminate FOUC and provide enhanced multilingual design control.
 * Version: 1.1.0
 * Author: Everyday Practice
 * Plugin URI: https://github.com/everyday-practice/Multilingual.js-for-WordPress
 * Author URI: https://everyday-practice.com
 * Text Domain: multilingual-wp
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Network: false
 */
if (!defined('ABSPATH'))
	exit;

/**
 * 플러그인 목록 화면에서 설정 링크 추가
 */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
	$url = admin_url('options-general.php?page=mlwp-settings');
	$settings_link = '<a href="' . esc_url($url) . '">' . __('Settings', 'multilingual-wp') . '</a>';
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

	// 기본 타입 처리
	$basic_types = ['en', 'ko', 'cn', 'jp', 'num', 'punct'];
	$selected_basic_types = isset($opts['types']) ? array_values(array_intersect($basic_types, (array) $opts['types'])) : $defaults['types'];
	
	// 커스텀 타입 자동 추가 (별도 체크 불필요)
	$custom_types = [];
	if (!empty($opts['custom_charsets']) && is_array($opts['custom_charsets'])) {
		foreach ($opts['custom_charsets'] as $customSet) {
			if (is_array($customSet)) {
				foreach ($customSet as $key => $data) {
					$custom_types[] = $key;
				}
			}
		}
	}
	
	// 기본 타입 + 커스텀 타입 자동 병합
	$types = array_merge($selected_basic_types, $custom_types);
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
	$src = plugin_dir_url(__FILE__) . 'multilingual-for-wordpress.js';
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
 * 서버사이드 래핑 유틸리티 집합
 */
/**
 * 래핑 대상 문자셋 패턴 맵 반환
 * @return array 언어/유형별 정규식 패턴
 */
function mlwp_get_patterns()
{
	// 기본 패턴
	$patterns = [
		'en' => '[A-Za-z]+',
		'ko' => '[\x{3131}-\x{318E}\x{AC00}-\x{D7A3}]+',
		'cn' => '[\x{4E00}-\x{9FBF}]+',
		'jp' => '[\x{3040}-\x{309F}\x{30A0}-\x{30FF}]+',
		'num' => '[0-9]+',
		'punct' => '[\[\]（）().#^\-&,;:@%*，、。」\'"‘’“”«»–—…]+',
	];
	
	// 커스텀 문자세트 병합 (무한 재귀 방지를 위해 직접 옵션 가져오기)
	$opts = get_option('mlwp_options', []);
	if (!empty($opts['custom_charsets']) && is_array($opts['custom_charsets'])) {
		foreach ($opts['custom_charsets'] as $customSet) {
			if (is_array($customSet)) {
				foreach ($customSet as $key => $data) {
					if (isset($data['charset']) && is_string($data['charset'])) {
						$charset = $data['charset'];
						
						// 완성된 정규식 패턴인지 확인 (양 끝에 구분자가 있고 플래그가 있는 경우)
						if (preg_match('/^\/.*\/[gimuy]*$/', $charset)) {
							// 완성된 정규식이면 구분자 제거 후 사용
							$patterns[$key] = preg_replace('/^\/(.*)\/[gimuy]*$/', '$1', $charset);
						} else {
							// 단순 문자열로 처리 - 모든 문자를 안전하게 이스케이프
							$chars = preg_split('//u', $charset, -1, PREG_SPLIT_NO_EMPTY);
							if (count($chars) === 1) {
								// 단일 문자면 이스케이프 후 + 추가
								$patterns[$key] = preg_quote($charset, '/') . '+';
							} else {
								// 여러 문자면 각각 이스케이프 후 문자 클래스로 변환
								$escaped_chars = array_map(function($char) {
									return preg_quote($char, '/');
								}, $chars);
								$patterns[$key] = '[' . implode('', $escaped_chars) . ']+';
							}
						}
						
						// 패턴 유효성 검사
						$test_pattern = '/' . $patterns[$key] . '/u';
						if (@preg_match($test_pattern, '') === false) {
							unset($patterns[$key]); // 유효하지 않은 패턴 제거
						}
					}
				}
			}
		}
	}
	
	return $patterns;
}

/**
 * 선택된 유형 배열을 하나의 멀티-캡처 정규식으로 병합
 * @param array $types 적용할 유형 리스트
 * @return string|null 유니코드 정규식 또는 null
 */
function mlwp_build_combined_regex(array $types)
{
	$all = mlwp_get_patterns();
	$basic_types = ['en', 'ko', 'cn', 'jp', 'num', 'punct'];
	$custom_types = array_diff($types, $basic_types);
	$basic_active = array_intersect($types, $basic_types);
	
	$parts = [];
	
	// 1) 커스텀 타입 먼저 처리 (우선순위 높음)
	foreach ($custom_types as $t) {
		if (isset($all[$t])) {
			$parts[] = '(' . $all[$t] . ')';
		}
	}
	
	// 2) 기본 타입 나중에 처리
	foreach ($basic_active as $t) {
		if (isset($all[$t])) {
			$parts[] = '(' . $all[$t] . ')';
		}
	}
	
	if (empty($parts))
		return null;
	return '/' . implode('|', $parts) . '/u';
}

/**
 * 래핑에서 항상 제외할 태그인지 확인
 * @param string $tagName 태그명
 * @return bool 제외 여부
 */
function mlwp_is_excluded_tag($tagName)
{
	$tagName = strtolower($tagName);
	return in_array($tagName, ['script', 'style', 'code', 'pre', 'textarea', 'kbd', 'samp'], true);
}

/**
 * 부모가 이미 래핑된 span인지 검사하여 중복 래핑 방지
 * @param DOMNode $node 현재 텍스트 노드
 * @param string $classPrefix 클래스 접두사
 * @return bool 부모가 래핑된 span인지 여부
 */
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

/**
 * 보호해야 할 토큰([...], {{...}})을 기준으로 텍스트를 세그먼트 분리
 * 보호 세그먼트는 래핑 대상에서 제외
 * @param string $text 원본 텍스트
 * @return array 세그먼트 배열 [text, protected]
 */
function mlwp_split_protected_bracket_segments($text)
{
	$segments = [];
	$length = mb_strlen($text, 'UTF-8');
	$cursor = 0;

	$protectedMatches = [];

	// 1) {{ ... }} 템플릿 토큰은 항상 보호
	if (preg_match_all('/\{\{[^}]+\}\}/u', $text, $mTpl, PREG_OFFSET_CAPTURE)) {
		foreach ($mTpl[0] as $mm) {
			$protectedMatches[] = $mm; // [matchedStr, byteOffset]
		}
	}

	// 2) whitelist된 숏코드만 보호
	$cfg = function_exists('mlwp_get_config') ? mlwp_get_config() : [];
	$whitelist = isset($cfg['shortcode_whitelist']) && is_array($cfg['shortcode_whitelist'])
		? array_values(array_filter(array_map('trim', $cfg['shortcode_whitelist'])))
		: [];

	if (!empty($whitelist) && function_exists('get_shortcode_regex')) {
		$scRegex = '/' . get_shortcode_regex() . '/s';
		if (preg_match_all($scRegex, $text, $mSc, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
			foreach ($mSc as $match) {
				$fullMatch = $match[0][0];
				$offsetBytes = $match[0][1];
				$tagName = isset($match[2][0]) ? $match[2][0] : '';
				if ($tagName !== '' && in_array($tagName, $whitelist, true)) {
					$protectedMatches[] = [$fullMatch, $offsetBytes];
				}
			}
		}
	}

	if (empty($protectedMatches)) {
		return [['text' => $text, 'protected' => false]];
	}

	usort($protectedMatches, function ($a, $b) {
		return $a[1] <=> $b[1];
	});

	foreach ($protectedMatches as $m) {
		$matchStr = $m[0];
		$offsetBytes = $m[1];

		$start = mb_strlen(mb_strcut($text, 0, $offsetBytes, 'UTF-8'), 'UTF-8');

		if ($start > $cursor) {
			$segments[] = [
				'text' => mb_substr($text, $cursor, $start - $cursor, 'UTF-8'),
				'protected' => false
			];
		}
		$segments[] = ['text' => $matchStr, 'protected' => true];
		$cursor = $start + mb_strlen($matchStr, 'UTF-8');
	}

	if ($cursor < $length) {
		$segments[] = [
			'text' => mb_substr($text, $cursor, $length - $cursor, 'UTF-8'),
			'protected' => false
		];
	}

	return $segments;
}

/**
 * 텍스트 노드 내에서 지정 정규식과 일치하는 부분을 유형별 span으로 감싸기
 * 보호 세그먼트를 존중하며, 실제 매칭이 없으면 변경하지 않음
 * @return bool 변경 여부
 */
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

/**
 * 매우 제한된 CSS 선택자를 XPath로 변환 (태그.클래스.클래스, 공백은 후손 결합자)
 * @param string $selector CSS 유사 선택자
 * @return string|null XPath 표현식
 */
function mlwp_selector_to_xpath($selector)
{
	$selector = trim($selector);
	if ($selector === '')
		return null;
	
	// 단일 클래스 선택자만 처리 (공백이 없는 경우만)
	if ($selector[0] === '.' && strpos($selector, ' ') === false) {
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

/**
 * 선택자 배열에서 클래스 토큰만 추출해 중복 제거한 리스트 반환
 * @param array $selectors 선택자 배열
 * @return array 클래스명 리스트
 */
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

/**
 * 주어진 HTML 조각 내에서 특정 선택자에 해당하는 요소들의 텍스트를 서버사이드로 래핑
 * @param string $html 원본 HTML 조각
 * @param array $selectors 대상 선택자 배열
 * @param array $types 적용 유형
 * @param string $classPrefix 클래스 접두사
 * @param array $excludeSelectors 하위에서 제외할 선택자 배열
 * @return string 변환된 HTML
 */
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
	
	// 동적 클래스를 고려한 확장된 예외 선택자 사용
	$expandedExcludeSelectors = mlwp_expand_exclude_selectors_for_dynamic_classes($excludeSelectors);
	
	// 타입 순서를 정규식과 동일하게 정렬 (커스텀 먼저, 기본 나중)
	$basic_types = ['en', 'ko', 'cn', 'jp', 'num', 'punct'];
	$custom_types = array_diff($types, $basic_types);
	$basic_active = array_intersect($types, $basic_types);
	$sorted_types = array_merge($custom_types, $basic_active);
	
	$regex = mlwp_build_combined_regex($types);
	foreach ($targetNodes as $el) {
		// Build exclude set per root, using expanded selectors
		$excludeNodeSet = [];

		if (!empty($expandedExcludeSelectors)) {
			foreach ($expandedExcludeSelectors as $exSel) {
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
				mlwp_wrap_text_node($doc, $node, $regex, $sorted_types, $classPrefix);
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

/**
 * 설정된 자동 선택자 기준으로 콘텐츠 조각을 래핑
 * @param string $html 원본 HTML
 * @return string 변환된 HTML
 */
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

/**
 * 숏코드 출력에 대한 안전한 기본 선택자 기반 래핑
 * @param string $html 숏코드 렌더 결과
 * @return string 변환된 HTML
 */
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

/**
 * 예외 선택자를 확장하여 동적 클래스 변형 포함
 * @param array $excludeSelectors 원본 예외 선택자 배열
 * @return array 확장된 예외 선택자 배열
 */
function mlwp_expand_exclude_selectors_for_dynamic_classes(array $excludeSelectors)
{
	$expanded = [];
	
	foreach ($excludeSelectors as $selector) {
		$expanded[] = $selector; // 원본 유지
		
		// 복합 클래스가 있는 토큰을 찾아서 각 클래스를 개별적으로 제거한 변형 생성
		$tokens = preg_split('/\s+/', trim($selector));
		
		foreach ($tokens as $i => $token) {
			// 복합 클래스 토큰 감지 (.class1.class2 형태)
			if (preg_match('/^\.([a-zA-Z0-9_-]+)\.([a-zA-Z0-9_-]+.*)/', $token)) {
				// 복합 클래스 토큰을 단순화
				$classes = explode('.', ltrim($token, '.'));
				
				// 각 클래스를 하나씩 제거한 버전들 생성
				for ($j = 0; $j < count($classes); $j++) {
					$reducedClasses = $classes;
					array_splice($reducedClasses, $j, 1);
					
					if (!empty($reducedClasses)) {
						$newTokens = $tokens;
						$newTokens[$i] = '.' . implode('.', $reducedClasses);
						$newSelector = implode(' ', $newTokens);
						$expanded[] = $newSelector;
					}
				}
				
				// 모든 클래스를 제거하고 나머지 토큰들만 남긴 버전
				$remainingTokens = $tokens;
				array_splice($remainingTokens, $i, 1);
				if (!empty($remainingTokens)) {
					$baseSelector = implode(' ', $remainingTokens);
					$expanded[] = trim($baseSelector);
				}
			}
		}
	}
	
	$result = array_unique($expanded);
	return $result;
}

// 필터: 블록, 숏코드, 본문(the_content), 메뉴 등의 서버사이드 래핑
add_filter('render_block', function ($block_content, $block) {
	if (is_admin())
		return $block_content;
	if (empty($block_content))
		return $block_content;
	return mlwp_server_wrap_content($block_content);
}, 20, 2);

add_filter('do_shortcode_tag', function ($output, $tag, $attr) {
	if (is_admin())
		return $output;
	if (empty($output))
		return $output;
	return mlwp_server_wrap_content($output);
}, 20, 3);

add_filter('the_content', function ($content) {
	if (is_admin())
		return $content;
	if (empty($content))
		return $content;
	return mlwp_server_wrap_content($content);
}, 20);

/**
 * 기본 훅 등록 (항상 실행)
 */
function mlwp_register_hooks()
{
	// 분리된 JS 로드 및 설정 주입
	add_action('wp_enqueue_scripts', 'mlwp_enqueue_scripts', 20);
}

/**
 * Load plugin textdomain for internationalization
 */
function mlwp_load_textdomain() {
	load_plugin_textdomain('multilingual-wp', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('plugins_loaded', 'mlwp_load_textdomain');

// 기본 훅 등록 (항상 실행)
add_action('init', 'mlwp_register_hooks');
if (is_admin())
	require_once __DIR__ . '/admin/settings.php';

/**
 * 공통 처리: admin-ajax.php 출력과 REST API 응답을 가로채어
 * AJAX/REST 기반 업데이트에도 서버사이드 래핑을 적용
 */
// 1) admin-ajax.php: 출력 버퍼 가로채기
add_action('init', function () {
	if (!wp_doing_ajax()) {
		return;
	}
	// wp-admin에서 발생한 AJAX(에디터, 설정 등)에는 개입하지 않음
	$ref = isset($_SERVER['HTTP_REFERER']) ? (string) $_SERVER['HTTP_REFERER'] : '';
	if ($ref && strpos($ref, '/wp-admin/') !== false) {
		return;
	}
	ob_start(function ($buffer) {
		if (!function_exists('mlwp_server_wrap_content')) {
			return $buffer;
		}
		// 1) JSON 응답일 경우, 예상 키에서 HTML 텍스트를 찾아 래핑
		$decoded = json_decode($buffer, true);
		if (is_array($decoded)) {
			$keys = ['template', 'html', 'content', 'rendered', 'markup', 'output', 'data', 'result', 'response', 'body', 'posts', 'items'];
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
		// 2) JSON이 아니고 HTML 문자라면 그대로 래핑 시도
		if (is_string($buffer) && strpos($buffer, '<') !== false) {
			return mlwp_server_wrap_content($buffer);
		}
		return $buffer;
	});
}, 0);

// 2) REST API: 응답 데이터 가로채기 및 선택적 래핑
add_filter('rest_post_dispatch', function ($result, $server, $request) {
	if (!function_exists('mlwp_server_wrap_content')) {
		return $result;
	}
	if (!($result instanceof WP_REST_Response)) {
		return $result;
	}
	// 에디터 컨텍스트 및 블록 렌더러 프리뷰에서는 래핑하지 않음
	$route = is_object($request) ? $request->get_route() : '';
	$context = is_object($request) ? $request->get_param('context') : '';
	if ((is_string($route) && strpos($route, '/wp/v2/block-renderer') === 0) || $context === 'edit') {
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
