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
    __('Multilingual Settings', 'multilingual-wp'),
    __('Multilingual Settings', 'multilingual-wp'),
    'manage_options',
    'mlwp-settings',
    'mlwp_render_settings_page'
  );
});

add_action('admin_init', function () {
  register_setting('mlwp_settings_group', MLWP_OPTION_KEY, [
    'sanitize_callback' => 'mlwp_sanitize_options'
  ]);

  add_settings_section('mlwp_main', '', function () {
    echo '<div style="margin: 15px 0; padding: 0 15px; border: 1px #fff solid; background-color: #fff;">';
    echo '<p>' . __('Server-side text wrapping for consistent typography across languages.', 'multilingual-wp') . '<br>' . sprintf(__('Reimplemented the open-source library %s for WordPress, applying server-side wrapping to prevent style delay issues (FOUC).', 'multilingual-wp'), '<a style="background-color: #e5e5e5; padding: 2px 4px; border-radius: 4px; color: #000000; text-decoration: none; margin-right: 2px; font-size: 12px;" href="https://github.com/multilingualjs/multilingual.js" target="_blank">multilingual.js</a>') . '</p>';
    echo '<p>' . __('Configure text wrapping types, class prefix, auto-apply selectors, exclusion selectors, shortcode whitelist, and custom character sets.', 'multilingual-wp');
    echo '<br>' . sprintf(__('For detailed usage instructions, please refer %s.', 'multilingual-wp'), '<a style="background-color: #e5e5e5; padding: 2px 4px; border-radius: 4px; color: #000000; text-decoration: none; margin-right: 2px; font-size: 12px;" href="https://github.com/everyday-practice/Multilingual.js-for-WordPress" target="_blank">' . __('here', 'multilingual-wp') . '</a>') . '</p>';
    echo '</div>';
  }, 'mlwp-settings');

  add_settings_field('mlwp_types', __('Text Wrapping Types', 'multilingual-wp'), 'mlwp_field_types', 'mlwp-settings', 'mlwp_main');
  add_settings_field('mlwp_class_prefix', __('CSS Class Prefix', 'multilingual-wp'), 'mlwp_field_class_prefix', 'mlwp-settings', 'mlwp_main');
  add_settings_field('mlwp_auto_selectors', __('Auto-Apply Selectors', 'multilingual-wp'), 'mlwp_field_auto_selectors', 'mlwp-settings', 'mlwp_main');
  add_settings_field('mlwp_exclude_selectors', __('Exclusion Selectors', 'multilingual-wp'), 'mlwp_field_exclude_selectors', 'mlwp-settings', 'mlwp_main');
  add_settings_field('mlwp_shortcode_whitelist', __('Shortcode Whitelist', 'multilingual-wp'), 'mlwp_field_shortcode_whitelist', 'mlwp-settings', 'mlwp_main');
  add_settings_field('mlwp_custom_charsets', __('Custom Character Sets', 'multilingual-wp'), 'mlwp_field_custom_charsets', 'mlwp-settings', 'mlwp_main');
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
    'en' => __('English (en)', 'multilingual-wp'), 
    'ko' => __('Korean (ko)', 'multilingual-wp'), 
    'cn' => __('Chinese (cn)', 'multilingual-wp'), 
    'jp' => __('Japanese (jp)', 'multilingual-wp'), 
    'num' => __('Numbers (num)', 'multilingual-wp'), 
    'punct' => __('Punctuation (punct)', 'multilingual-wp')
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
    echo '<div style="max-width:800px;margin-top:10px;padding:8px 10px;background:#ffffff;border:1px solid#f46565;box-sizing: border-box;font-size: 13px;line-height: 1.3;">';
    echo '<strong>' . __('Custom Character Sets (Auto-enabled):', 'multilingual-wp') . '</strong><br>';
    foreach ($custom_types as $key => $label) {
      echo '<span style="display:inline-block;margin-right:15px;color:#c91c1c;">✓ ' . esc_html($label) . '</span>';
    }
    echo '</div>';
  }
}

function mlwp_field_class_prefix()
{
  $opts = mlwp_get_options();
  
  // 아코디언 형식으로 간단한 설명 추가
  echo '<details style="max-width: 800px; margin-bottom: 15px;">';
  echo '<summary style="padding: 7px 12px; background: #ffffff; cursor: pointer; color: #666; font-weight: 600; font-size: 13px;">';
  echo __('View Usage', 'multilingual-wp');
  echo '</summary>';
  echo '<div style="padding: 13px; background: #fff; border-top: 1px solid #ddd; font-size: 13px;">';
  
  echo '<p style="margin: 0 0 12px 0; font-size: 13px;">' . __('This prefix is used for all automatically generated CSS classes. Choose a short, unique prefix to avoid conflicts with existing styles.', 'multilingual-wp') . '</p>';
  
  echo '<div style="margin-top: 15px; padding: 8px 10px; background: #fff2db; border-radius: 3px; font-size: 13px; line-height: 1.5;">';
  echo '<strong>' . __('Usage:', 'multilingual-wp') . '</strong> ' . __('Enter "ml" to generate classes like ml-en, ml-ko, ml-num, etc.', 'multilingual-wp');
  echo '</div>';
  
  echo '</div>';
  echo '</details>';
  
  echo '<input type="text" name="' . esc_attr(MLWP_OPTION_KEY) . '[class_prefix]" value="' . esc_attr($opts['class_prefix']) . '" class="regular-text" style="width: 100%; max-width: 800px; border-radius: 0;" />';
}

function mlwp_field_auto_selectors()
{
  $opts = mlwp_get_options();
  $val = implode("\n", (array) $opts['auto_selectors']);
  
  // 아코디언 형식으로 자세한 설명 추가
  echo '<details style="max-width: 800px; margin-bottom: 15px;">';
    echo '<summary style="padding: 7px 12px; background: #ffffff; cursor: pointer; color: #666; font-weight: 600; font-size: 13px;">';
  echo __('View Usage', 'multilingual-wp');
  echo '</summary>';
  echo '<div style="padding: 13px; background: #fff; border-top: 1px solid #ddd; font-size: 13px;">';
  
  echo '<p style="margin: 0 0 12px 0; font-size: 13px;">' . __('CSS selectors that automatically apply text wrapping when the page loads. Configure which elements should have their text processed for multilingual typography.', 'multilingual-wp') . '</p>';
       
  echo '<div style="margin-top: 15px; padding: 8px 10px; background: #fff2db; border-radius: 3px; font-size: 13px; line-height: 1.5;">';
  echo '<strong>' . __('Usage:', 'multilingual-wp') . '</strong> ' . __('Enter one CSS selector per line. These elements will have their text automatically wrapped with language-specific classes.', 'multilingual-wp');
  echo '</div>';
  
  echo '</div>';
  echo '</details>';
  
  echo '<textarea style="width: 100%; max-width: 800px; border-radius: 0;" name="' . esc_attr(MLWP_OPTION_KEY) . '[auto_selectors]" rows="6" class="large-text code">' . esc_textarea($val) . '</textarea>';
}

function mlwp_field_exclude_selectors()
{
  $opts = mlwp_get_options();
  $val = implode("\n", (array) $opts['exclude_selectors']);
  
  // 아코디언 형식으로 자세한 설명 추가
  echo '<details style="max-width: 800px; margin-bottom: 15px;">';
  echo '<summary style="padding: 7px 12px; background: #ffffff; cursor: pointer; color: #666; font-weight: 600; font-size: 13px;">';
  echo __('View Usage', 'multilingual-wp');
  echo '</summary>';
  echo '<div style="padding: 13px; background: #fff; border-top: 1px solid #ddd; font-size: 13px;">';
  
  echo '<p style="margin: 0 0 12px 0; font-size: 13px;">' . __('CSS selectors that will be excluded from automatic text wrapping, even if they match auto-apply selectors. Use this to protect specific areas from processing.', 'multilingual-wp') . '</p>';
  
  echo '<div style="margin-top: 15px; padding: 8px 10px; background: #fff2db; border-radius: 3px; font-size: 13px; line-height: 1.5;">';
  echo '<strong>' . __('Usage:', 'multilingual-wp') . '</strong> ' . __('Enter one CSS selector per line. These elements and their children will be excluded from text wrapping.', 'multilingual-wp') . '<br>';
  echo '<strong>' . __('Important:', 'multilingual-wp') . '</strong> ' . __('Exclusion rules apply to the selected element AND all its child elements.', 'multilingual-wp');
  echo '</div>';
  
  echo '</div>';
  echo '</details>';
  
  echo '<textarea style="width: 100%; max-width: 800px; border-radius: 0;" name="' . esc_attr(MLWP_OPTION_KEY) . '[exclude_selectors]" rows="4" class="large-text code">' . esc_textarea($val) . '</textarea>';
}

function mlwp_field_shortcode_whitelist()
{
  $opts = mlwp_get_options();
  $val = implode("\n", (array) $opts['shortcode_whitelist']);
  
  // 아코디언 형식으로 자세한 설명 추가
  echo '<details style="max-width: 800px; margin-bottom: 15px;">';
  echo '<summary style="padding: 7px 12px; background: #ffffff; cursor: pointer; color: #666; font-weight: 600; font-size: 13px;">';
  echo __('View Usage', 'multilingual-wp');
  echo '</summary>';
  echo '<div style="padding: 13px; background: #fff; border-top: 1px solid #ddd; font-size: 13px;">';
  
  echo '<p style="margin: 0 0 12px 0; font-size: 13px;">' . __('Use this when applying multilingual typography to content within [...] format shortcodes from themes or plugins. Prevents text wrapping from being applied when brackets are recognized as punctuation.', 'multilingual-wp') . '</p>';
  
  echo '<div style="margin-top: 15px; padding: 8px 10px; background: #fff2db; border-radius: 3px; font-size: 13px; line-height: 1.5;">';
  echo '<strong>' . __('Usage:', 'multilingual-wp') . '</strong> ' . __('Enter shortcode tag names one per line (without brackets []), example: [post_content] → post_content', 'multilingual-wp') . '<br>';
  echo '<strong>' . __('Important:', 'multilingual-wp') . '</strong> ' . __('Only add shortcodes that output simple text content. Avoid shortcodes with complex HTML, forms, or interactive elements.', 'multilingual-wp');
  echo '</div>';
  
  echo '</div>';
  echo '</details>';
  
  echo '<textarea style="width: 100%; max-width: 800px; border-radius: 0;" name="' . esc_attr(MLWP_OPTION_KEY) . '[shortcode_whitelist]" rows="4" class="large-text code">' . esc_textarea($val) . '</textarea>';
}

function mlwp_field_custom_charsets()
{
  $opts = mlwp_get_options();
  
  // 아코디언 형식으로 자세한 설명 추가
  echo '<details style="max-width: 800px; margin-bottom: 15px;">';
  echo '<summary style="padding: 7px 12px; background: #ffffff; cursor: pointer; color: #666; font-weight: 600; font-size: 13px;">';
  echo __('View Usage', 'multilingual-wp');
  echo '</summary>';
  echo '<div style="padding: 13px; background: #fff; border-top: 1px solid #ddd; font-size: 13px;">';
  
  echo '<p style="margin: 0 0 12px 0; font-size: 13px;">' . __('Define your own character types beyond the basic ones (English, Korean, Chinese, Japanese, numbers, punctuation). Create custom CSS classes for special characters that need unique styling.', 'multilingual-wp') . '</p>';
  
  echo '<div style="margin-bottom: 12px;"><strong>' . __('Format Examples:', 'multilingual-wp') . '</strong></div>';
  echo '<ul style="margin: 0 0 12px 0; line-height: 1.6;">';
  echo '<li><code>bullet:•◦▪▫</code> - ' . __('Bullet point symbols → .ml-bullet', 'multilingual-wp') . '</li>';
  echo '<li><code>arrow:→←↑↓</code> - ' . __('Arrow symbols → .ml-arrow', 'multilingual-wp') . '</li>';
  echo '</ul>';
  
  echo '<div style="margin-top: 15px; padding: 8px 10px; background: #fff2db; border-radius: 3px; font-size: 13px; line-height: 1.5;">';
  echo '<strong>' . __('Usage:', 'multilingual-wp') . '</strong> ' . __('Enter one per line in type:charset format. Each type becomes a CSS class (.ml-typename).', 'multilingual-wp');
  echo '</div>';
  
  echo '</div>';
  echo '</details>';
  
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
  echo '<textarea style="width: 100%; max-width: 800px; border-radius: 0;" name="' . esc_attr(MLWP_OPTION_KEY) . '[custom_charsets_simple]" rows="4" class="large-text code">' . esc_textarea($val) . '</textarea>';
}

function mlwp_render_settings_page()
{
  if (!current_user_can('manage_options'))
    return;
  echo '<div class="wrap">';
  echo '<h1>' . esc_html__('Multilingual for WordPress', 'multilingual-wp') . '</h1>';
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
