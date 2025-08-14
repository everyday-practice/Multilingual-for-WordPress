<?php
if (!defined('ABSPATH'))
  exit;

define('MLWP_OPTION_KEY', 'mlwp_options');

function mlwp_default_options()
{
  return [
    'types' => ['en', 'num', 'punct'],
    'class_prefix' => 'ml',
    'auto_selectors' => [],
    'exclude_selectors' => [],
    'shortcode_whitelist' => [],
    'custom_charsets' => [],
  ];
}

add_action('admin_menu', function () {
  add_options_page(
    'Multilingual 설정',
    'Multilingual 설정',
    'manage_options',
    'mlwp-settings',
    'mlwp_render_settings_page'
  );
});

add_action('admin_init', function () {
  register_setting('mlwp_settings_group', MLWP_OPTION_KEY, [
    'sanitize_callback' => 'mlwp_sanitize_options'
  ]);

  add_settings_section('mlwp_main', '기본 설정', function () {
    echo '<p>타입 래핑, 클래스 프리픽스, 자동 적용 셀렉터, 예외 셀렉터, 숏코드 화이트리스트를 설정합니다.</p>';
  }, 'mlwp-settings');

  add_settings_field('mlwp_types', '래핑 대상 타입', 'mlwp_field_types', 'mlwp-settings', 'mlwp_main');
  add_settings_field('mlwp_class_prefix', '클래스 프리픽스', 'mlwp_field_class_prefix', 'mlwp-settings', 'mlwp_main');
  add_settings_field('mlwp_auto_selectors', '자동 적용 셀렉터', 'mlwp_field_auto_selectors', 'mlwp-settings', 'mlwp_main');
  add_settings_field('mlwp_exclude_selectors', '예외 셀렉터(제외)', 'mlwp_field_exclude_selectors', 'mlwp-settings', 'mlwp_main');
  add_settings_field('mlwp_shortcode_whitelist', '숏코드 화이트리스트', 'mlwp_field_shortcode_whitelist', 'mlwp-settings', 'mlwp_main');
  add_settings_field('mlwp_custom_charsets', '커스텀 문자세트(JSON)', 'mlwp_field_custom_charsets', 'mlwp-settings', 'mlwp_main');
});

function mlwp_get_options()
{
  $defaults = mlwp_default_options();
  $opts = get_option(MLWP_OPTION_KEY, []);
  if (!is_array($opts))
    $opts = [];
  return array_merge($defaults, $opts);
}

function mlwp_field_types()
{
  $opts = mlwp_get_options();
  $all = ['en' => '영문(en)', 'ko' => '한글(ko)', 'cn' => '중문(cn)', 'jp' => '일문(jp)', 'num' => '숫자(num)', 'punct' => '문장부호(punct)'];
  echo '<fieldset>';
  foreach ($all as $key => $label) {
    $checked = in_array($key, (array) $opts['types'], true) ? 'checked' : '';
    echo '<label style="display:inline-block;margin-right:12px;">';
    echo '<input type="checkbox" name="' . esc_attr(MLWP_OPTION_KEY) . '[types][]" value="' . esc_attr($key) . '" ' . $checked . '> ' . esc_html($label);
    echo '</label>';
  }
  echo '</fieldset>';
}

function mlwp_field_class_prefix()
{
  $opts = mlwp_get_options();
  echo '<input type="text" name="' . esc_attr(MLWP_OPTION_KEY) . '[class_prefix]" value="' . esc_attr($opts['class_prefix']) . '" class="regular-text" />';
  echo '<p class="description">예: ml → <code>ml-en</code>, <code>ml-num</code></p>';
}

function mlwp_field_auto_selectors()
{
  $opts = mlwp_get_options();
  $val = implode("\n", (array) $opts['auto_selectors']);
  echo '<textarea name="' . esc_attr(MLWP_OPTION_KEY) . '[auto_selectors]" rows="6" class="large-text code">' . esc_textarea($val) . '</textarea>';
  echo '<p class="description">한 줄에 하나씩 입력 (.class, tag.class 등 단순 셀렉터 권장)</p>';
}

function mlwp_field_exclude_selectors()
{
  $opts = mlwp_get_options();
  $val = implode("\n", (array) $opts['exclude_selectors']);
  echo '<textarea name="' . esc_attr(MLWP_OPTION_KEY) . '[exclude_selectors]" rows="4" class="large-text code">' . esc_textarea($val) . '</textarea>';
  echo '<p class="description">한 줄에 하나씩 입력. 예: <code>.about-content .point-color</code> (해당 영역과 하위 요소는 래핑에서 제외)</p>';
}

function mlwp_field_shortcode_whitelist()
{
  $opts = mlwp_get_options();
  $val = implode("\n", (array) $opts['shortcode_whitelist']);
  echo '<textarea name="' . esc_attr(MLWP_OPTION_KEY) . '[shortcode_whitelist]" rows="4" class="large-text code">' . esc_textarea($val) . '</textarea>';
  echo '<p class="description">한 줄에 하나씩 숏코드 태그 입력 (예: post_content)</p>';
}

function mlwp_field_custom_charsets()
{
  $opts = mlwp_get_options();
  $json = json_encode($opts['custom_charsets'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  echo '<textarea name="' . esc_attr(MLWP_OPTION_KEY) . '[custom_charsets]" rows="6" class="large-text code">' . esc_textarea($json) . '</textarea>';
  echo '<p class="description">예: {"parentheses":{"className":"ml-parentheses","charset":"()（）"}}</p>';
}

function mlwp_render_settings_page()
{
  if (!current_user_can('manage_options'))
    return;
  echo '<div class="wrap">';
  echo '<h1>Multilingual 설정</h1>';
  echo '<form action="options.php" method="post">';
  settings_fields('mlwp_settings_group');
  do_settings_sections('mlwp-settings');
  submit_button();
  echo '</form></div>';
}

function mlwp_sanitize_options($input)
{
  $defaults = mlwp_default_options();
  $out = [];

  $allowed_types = ['en', 'ko', 'cn', 'jp', 'num', 'punct'];
  $out['types'] = [];
  if (!empty($input['types']) && is_array($input['types'])) {
    foreach ($input['types'] as $t) {
      $t = sanitize_text_field($t);
      if (in_array($t, $allowed_types, true))
        $out['types'][] = $t;
    }
  }
  if (empty($out['types']))
    $out['types'] = $defaults['types'];

  $out['class_prefix'] = isset($input['class_prefix']) ? preg_replace('/[^a-z0-9_\-]/i', '', $input['class_prefix']) : $defaults['class_prefix'];
  if ($out['class_prefix'] === '')
    $out['class_prefix'] = $defaults['class_prefix'];

  $out['auto_selectors'] = mlwp_textarea_to_array($input['auto_selectors'] ?? '', $defaults['auto_selectors']);
  $out['exclude_selectors'] = mlwp_textarea_to_array($input['exclude_selectors'] ?? '', $defaults['exclude_selectors']);
  $out['shortcode_whitelist'] = mlwp_textarea_to_array($input['shortcode_whitelist'] ?? '', $defaults['shortcode_whitelist']);

  $out['custom_charsets'] = $defaults['custom_charsets'];
  if (isset($input['custom_charsets'])) {
    $raw = $input['custom_charsets'];
    if (is_string($raw)) {
      $decoded = json_decode(wp_unslash($raw), true);
      if (is_array($decoded)) {
        $out['custom_charsets'] = $decoded;
      }
    } elseif (is_array($raw)) {
      $out['custom_charsets'] = $raw;
    }
  }

  return $out;
}

function mlwp_textarea_to_array($text, $fallback = [])
{
  if (!is_string($text))
    return $fallback;
  $lines = array_map('trim', preg_split('/\r\n|\r|\n/', $text));
  $lines = array_values(array_filter(array_unique($lines)));
  return !empty($lines) ? $lines : $fallback;
}

register_activation_hook(dirname(__FILE__, 2) . '/multilingual-js-for-wordpress.php', function () {
  if (get_option(MLWP_OPTION_KEY) === false) {
    add_option(MLWP_OPTION_KEY, mlwp_default_options());
  }
});
