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
    __('Multilingual Settings', 'multilingual-js-wp'),
    __('Multilingual Settings', 'multilingual-js-wp'),
    'manage_options',
    'mlwp-settings',
    'mlwp_render_settings_page'
  );
});

add_action('admin_init', function () {
  register_setting('mlwp_settings_group', MLWP_OPTION_KEY, [
    'sanitize_callback' => 'mlwp_sanitize_options'
  ]);

  add_settings_section('mlwp_main', __('Basic Settings', 'multilingual-js-wp'), function () {
    echo '<p>' . __('Configure text wrapping types, class prefix, auto-apply selectors, exclusion selectors, shortcode whitelist, and custom character sets.', 'multilingual-js-wp') . '</p>';
  }, 'mlwp-settings');

  add_settings_field('mlwp_types', __('Text Wrapping Types', 'multilingual-js-wp'), 'mlwp_field_types', 'mlwp-settings', 'mlwp_main');
  add_settings_field('mlwp_class_prefix', __('CSS Class Prefix', 'multilingual-js-wp'), 'mlwp_field_class_prefix', 'mlwp-settings', 'mlwp_main');
  add_settings_field('mlwp_auto_selectors', __('Auto-Apply Selectors', 'multilingual-js-wp'), 'mlwp_field_auto_selectors', 'mlwp-settings', 'mlwp_main');
  add_settings_field('mlwp_exclude_selectors', __('Exclusion Selectors', 'multilingual-js-wp'), 'mlwp_field_exclude_selectors', 'mlwp-settings', 'mlwp_main');
  add_settings_field('mlwp_shortcode_whitelist', __('Shortcode Whitelist', 'multilingual-js-wp'), 'mlwp_field_shortcode_whitelist', 'mlwp-settings', 'mlwp_main');
  add_settings_field('mlwp_custom_charsets', __('Custom Character Sets', 'multilingual-js-wp'), 'mlwp_field_custom_charsets', 'mlwp-settings', 'mlwp_main');
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
  $basic = [
    'en' => __('English (en)', 'multilingual-js-wp'), 
    'ko' => __('Korean (ko)', 'multilingual-js-wp'), 
    'cn' => __('Chinese (cn)', 'multilingual-js-wp'), 
    'jp' => __('Japanese (jp)', 'multilingual-js-wp'), 
    'num' => __('Numbers (num)', 'multilingual-js-wp'), 
    'punct' => __('Punctuation (punct)', 'multilingual-js-wp')
  ];
  
  // 커스텀 문자세트에서 추가 타입 수집 (표시용)
  $custom_types = [];
  if (!empty($opts['custom_charsets']) && is_array($opts['custom_charsets'])) {
    foreach ($opts['custom_charsets'] as $customSet) {
      if (is_array($customSet)) {
        foreach ($customSet as $key => $data) {
          if (!isset($basic[$key])) {
            $custom_types[$key] = $key;
          }
        }
      }
    }
  }
  
  echo '<fieldset>';
  // 기본 타입만 체크박스로 표시
  foreach ($basic as $key => $label) {
    $checked = in_array($key, (array) $opts['types'], true) ? 'checked' : '';
    echo '<label style="display:inline-block;margin-right:12px !important;">';
    echo '<input type="checkbox" name="' . esc_attr(MLWP_OPTION_KEY) . '[types][]" value="' . esc_attr($key) . '" ' . $checked . '> ' . esc_html($label);
    echo '</label>';
  }
  echo '</fieldset>';
  
  // 커스텀 타입은 읽기 전용으로 표시
  if (!empty($custom_types)) {
    echo '<div style="margin-top:10px;padding:10px;background:#ffffff;border-left:2px solid#c91c1c;">';
    echo '<strong>' . __('Custom Character Sets (Auto-enabled):', 'multilingual-js-wp') . '</strong><br>';
    foreach ($custom_types as $key => $label) {
      echo '<span style="display:inline-block;margin-right:15px;color:#c91c1c;">✓ ' . esc_html($label) . '</span>';
    }
    echo '</div>';
  }
  
  echo '<p class="description">' . __('Custom character sets are automatically applied when defined below.', 'multilingual-js-wp') . '</p>';
}

function mlwp_field_class_prefix()
{
  $opts = mlwp_get_options();
  echo '<input type="text" name="' . esc_attr(MLWP_OPTION_KEY) . '[class_prefix]" value="' . esc_attr($opts['class_prefix']) . '" class="regular-text" />';
  echo '<p class="description">' . __('Example: <code>ml</code> → <code>ml-en</code>, <code>ml-num</code>', 'multilingual-js-wp') . '</p>';
}

function mlwp_field_auto_selectors()
{
  $opts = mlwp_get_options();
  $val = implode("\n", (array) $opts['auto_selectors']);
  echo '<textarea name="' . esc_attr(MLWP_OPTION_KEY) . '[auto_selectors]" rows="6" class="large-text code">' . esc_textarea($val) . '</textarea>';
  echo '<p class="description">' . __('Enter one per line (simple selectors like .class, tag.class recommended)', 'multilingual-js-wp') . '</p>';
}

function mlwp_field_exclude_selectors()
{
  $opts = mlwp_get_options();
  $val = implode("\n", (array) $opts['exclude_selectors']);
  echo '<textarea name="' . esc_attr(MLWP_OPTION_KEY) . '[exclude_selectors]" rows="4" class="large-text code">' . esc_textarea($val) . '</textarea>';
  echo '<p class="description">' . __('Enter one per line.<br>Example: <code>.about-content .point-color</code> (excludes the area and its child elements from wrapping)', 'multilingual-js-wp') . '</p>';
}

function mlwp_field_shortcode_whitelist()
{
  $opts = mlwp_get_options();
  $val = implode("\n", (array) $opts['shortcode_whitelist']);
  echo '<textarea name="' . esc_attr(MLWP_OPTION_KEY) . '[shortcode_whitelist]" rows="4" class="large-text code">' . esc_textarea($val) . '</textarea>';
  echo '<p class="description">' . __('Enter shortcode tags one per line.<br>Example: <code>post_content</code>', 'multilingual-js-wp') . '</p>';
}

function mlwp_field_custom_charsets()
{
  $opts = mlwp_get_options();
  
  // 기존 JSON 형식을 간단한 형식으로 변환
  $simple_format = [];
  if (!empty($opts['custom_charsets']) && is_array($opts['custom_charsets'])) {
    foreach ($opts['custom_charsets'] as $customSet) {
      if (is_array($customSet)) {
        foreach ($customSet as $key => $data) {
          if (isset($data['charset'])) {
            $simple_format[] = $key . ':' . $data['charset'];
          }
        }
      }
    }
  }
  
  $val = implode("\n", $simple_format);
  echo '<textarea name="' . esc_attr(MLWP_OPTION_KEY) . '[custom_charsets_simple]" rows="4" class="large-text code">' . esc_textarea($val) . '</textarea>';
  echo '<p class="description">' . __('Enter one per line in <code>type:charset</code> format<br>Examples: <code>parentheses:{}</code>, <code>bullet:•</code>, <code>arrow:→←</code>', 'multilingual-js-wp') . '</p>';
  
  // 기존 JSON 형식도 유지 (고급 사용자용)
  echo '<details style="margin-top:15px;"><summary style="cursor:pointer;font-weight:bold;">' . __('Advanced Settings (JSON Format)', 'multilingual-js-wp') . '</summary>';
  echo '<textarea name="' . esc_attr(MLWP_OPTION_KEY) . '[custom_charsets]" rows="6" class="large-text code" style="margin-top:10px;">' . esc_textarea(json_encode($opts['custom_charsets'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) . '</textarea>';
  echo '<p class="description">' . __('Direct JSON input (allows detailed settings like className)', 'multilingual-js-wp') . '</p>';
  echo '</details>';
}

function mlwp_render_settings_page()
{
  if (!current_user_can('manage_options'))
    return;
  echo '<div class="wrap">';
  echo '<h1>' . esc_html__('Multilingual Settings', 'multilingual-js-wp') . '</h1>';
  echo '<form action="options.php" method="post">';
  settings_fields('mlwp_settings_group');
  do_settings_sections('mlwp-settings');
  submit_button();
  echo '</form></div>';
}

function mlwp_sanitize_options($input)
{
  // Verify nonce for security
  if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'mlwp_settings_group-options')) {
    return false;
  }
  
  $defaults = mlwp_default_options();
  $out = [];

  $basic_types = ['en', 'ko', 'cn', 'jp', 'num', 'punct'];
  
  // 커스텀 타입은 항상 허용 (별도 체크 불필요)
  $allowed_types = $basic_types;
  
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

  $out['auto_selectors'] = mlwp_textarea_to_array(sanitize_textarea_field($input['auto_selectors'] ?? ''), $defaults['auto_selectors']);
  $out['exclude_selectors'] = mlwp_textarea_to_array(sanitize_textarea_field($input['exclude_selectors'] ?? ''), $defaults['exclude_selectors']);
  $out['shortcode_whitelist'] = mlwp_textarea_to_array(sanitize_textarea_field($input['shortcode_whitelist'] ?? ''), $defaults['shortcode_whitelist']);

  $out['custom_charsets'] = $defaults['custom_charsets'];
  
  // 1) 간단한 형식 처리 (우선순위) - 빈 값도 처리
  if (isset($input['custom_charsets_simple'])) {
    $simple_text = trim(sanitize_textarea_field($input['custom_charsets_simple']));
    
    if (empty($simple_text)) {
      // 빈 값이면 커스텀 문자세트 완전 삭제
      $out['custom_charsets'] = [];
    } else {
      // 내용이 있으면 파싱 처리
      $simple_lines = array_filter(array_map('trim', explode("\n", $simple_text)));
      $custom_charsets = [];
      
      foreach ($simple_lines as $line) {
        if (strpos($line, ':') !== false) {
          list($type, $charset) = array_map('trim', explode(':', $line, 2));
          $type = sanitize_key($type);
          $charset = sanitize_text_field($charset);
          if ($type && $charset) {
            $custom_charsets[] = [
              $type => [
                'className' => 'ml-' . $type,
                'charset' => $charset
              ]
            ];
          }
        }
      }
      
      $out['custom_charsets'] = $custom_charsets;
    }
  } 
  // 2) JSON 형식 처리 (간단한 형식이 없을 때만)
  elseif (isset($input['custom_charsets'])) {
    $raw = $input['custom_charsets'];
    if (is_string($raw)) {
      $raw = trim($raw);
      if (empty($raw)) {
        // JSON 필드가 비어있으면 삭제
        $out['custom_charsets'] = [];
      } else {
        $decoded = json_decode(wp_unslash($raw), true);
        if (is_array($decoded)) {
          $out['custom_charsets'] = $decoded;
        }
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
