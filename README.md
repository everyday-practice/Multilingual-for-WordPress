# Multilingual.js for WordPress

텍스트에서 영문/숫자/기호를 유형별로 <span>으로 서버사이드 래핑합니다.
첫 페인트 전에 적용되어 JS 로 인한 깜박임(FOUC)이 없습니다.

## 주요 기능

- **영문**: .ml-en (English [a-zA-Z]+)
- **한글**: .ml-ko (Korean [ㄱ-ㅎ가-힣ㅏ-ㅣ]+)
- **중문**: .ml-cn (Chinese [\u4E00-\u9FBF]+)
- **일문**: .ml-jp (Japanese [\u3040-\u309F\u30A0-\u30FF]+)
- **숫자**: .ml-num (Numeric [0-9]+)
- **문장부호**: .ml-punct (Punctuations)
- **사용자 정의**: custom_charsets로 추가 가능
- **자동 적용**: CSS 선택자로 지정된 요소에 자동 적용
- **독립 실행**: 다른 플러그인과 상관없이 항상 작동
- code/pre/style/textarea 등은 제외

## 요구 사항

- WordPress 6.x 이상

## 설치

1. 이 폴더를 `wp-content/mu-plugins/multilingual-js-for-wordpress/`에 업로드
2. 같은 경로 `wp-content/mu-plugins/`에 `multilingual-js-for-wordpress-loader.php` 파일을 추가
   ```php
   <?php
   if ( defined('WPMU_PLUGIN_DIR') ) {
     require_once WPMU_PLUGIN_DIR . '/multilingual-js-for-wordpress/multilingual-js-for-wordpress.php';
   }
   ```

## 사용법

### 자동 적용 (CSS 선택자)

`.project_text` 클래스가 있는 요소에 자동으로 적용됩니다:

```html
<div class="project_text">Hello World 123! 안녕하세요</div>
```

### 지원하는 types

- `en`: 영문
- `ko`: 한글
- `cn`: 중문
- `jp`: 일문
- `num`: 숫자
- `punct`: 문장부호

## 설정

`multilingual-js-for-wordpress.php` 파일에서 다음 설정을 조정할 수 있습니다:

```php
'types'        => ['en', 'ko', 'num', 'punct'],     // 감쌀 유형 선택
'class_prefix' => 'ml',                             // 클래스 접두사
'auto_selectors' => ['.project_text'],              // 자동 적용할 CSS 선택자들
'custom_charsets' => [                              // 사용자 정의 문자세트
    'parentheses' => [
        'className' => 'ml-parentheses',
        'charset' => '()（）'
    ]
]
```

## 사용 예시

### CSS 스타일링

```css
/* 기본 타이포그래피 */
body {
  font-family: 'Noto Sans KR', sans-serif;
  font-size: 16px;
  line-height: 1.6;
}

/* 영문 스타일 */
.ml-en {
  font-family: 'Playfair Display', serif;
  font-weight: 600;
}

/* 숫자 스타일 */
.ml-num {
  font-family: 'Roboto Mono', monospace;
  color: #007cba;
}

/* 문장부호 스타일 */
.ml-punct {
  font-weight: bold;
  color: #d63638;
}

/* 사용자 정의 문자세트 */
.ml-parentheses {
  position: relative;
  top: -0.05em;
  letter-spacing: -0.1em;
}
```

## multilingual.js 호환성

이 플러그인은 [multilingual.js](https://github.com/multilingualjs/multilingual.js) 라이브러리의 문자세트 패턴과 클래스 이름을 따릅니다.

