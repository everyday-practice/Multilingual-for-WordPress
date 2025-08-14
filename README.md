# 🌐 Multilingual.js for WordPress

[![WordPress](https://img.shields.io/badge/WordPress-6.x%2B-21759B?logo=wordpress&logoColor=white)](#) [![Type](https://img.shields.io/badge/Type-Plugin-blue)](#) [![Multilingual](https://img.shields.io/badge/Multilingual-i18n%2Fl10n-4CAF50)](#) [![Typography](https://img.shields.io/badge/Typography-Text%20wrapping-8E44AD)](#) [![Performance](https://img.shields.io/badge/Performance-Optimized-FF9800)](#) [![Accessibility](https://img.shields.io/badge/Accessibility-a11y-795548)](#)

서버사이드에서 텍스트(영문/숫자/문장부호/한글/중문/일문 등)를 유형별로 `<span>`으로 래핑하여 첫 렌더부터 일관된 타이포그래피를 제공합니다. 클라이언트 사이드 스크립트가 보조적으로 동작해 동적 DOM 업데이트에도 대응합니다. 더 이상 MU 플러그인이 아닌 일반 플러그인입니다.

## 📋 요구 사항

- WordPress 6.x 이상

## 🛠️ 설치

1. 폴더를 `wp-content/plugins/multilingual-js-for-wordpress/`에 업로드
2. 관리자에서 플러그인을 활성화: `플러그인 → 설치된 플러그인 → Multilingual.js for WordPress → 활성화`

## ⚙️ 설정 위치

- `설정 → Multilingual 설정`

여기서 다음 옵션을 조정할 수 있습니다.

- **래핑 대상 타입(types)**: `en`, `ko`, `cn`, `jp`, `num`, `punct`
  - 기본값: `['en', 'num', 'punct']`
- **클래스 프리픽스(class_prefix)**: 기본 `ml`
  - 예: `ml` → `ml-en`, `ml-num`, `ml-punct` …
- **자동 적용 셀렉터(auto_selectors)**: 래핑을 자동 적용할 CSS 셀렉터 목록
  - 단순 셀렉터 권장: `.class`, `tag.class`
- **예외 셀렉터(exclude_selectors)**: 래핑에서 제외할 영역의 셀렉터 목록
  - 예: `.about-content .point-color`
- **숏코드 화이트리스트(shortcode_whitelist)**: 지정한 숏코드 출력에 서버사이드 래핑 적용
  - 예: `post_content`

> 참고: 관리자 화면에서 “커스텀 문자세트(custom_charsets)” 입력란이 있으나, 현재 버전(1.1.0)에서는 서버/클라이언트 래퍼가 해당 설정을 사용하지 않습니다(향후 확장 예약).

## 🧠 동작 개요

- 프론트엔드에서 플러그인 스크립트를 등록하고, 설정을 `window.MLWP_CFG`로 인라인 주입합니다.
- 서버사이드 래핑은 다음에 적용됩니다.
  - 블록 렌더 결과(`render_block`)
  - 본문 콘텐츠(`the_content`)
  - 화이트리스트에 포함된 숏코드 출력(`do_shortcode_tag`)
- AJAX/REST 응답에서도 HTML/템플릿 같은 문자열을 찾아 서버사이드 래핑을 시도합니다.
  - `admin-ajax.php` 출력 버퍼 후처리
  - `rest_post_dispatch` 응답 데이터 후처리
- 클라이언트 사이드 스크립트는 `MutationObserver`로 동적 DOM 변화를 감지하며, `auto_selectors`에 매칭되는 요소에 보조 래핑을 수행합니다.

## 🔤 지원 타입과 클래스

- **영문(en)**: `ml-en` (`[A-Za-z]+`)
- **한글(ko)**: `ml-ko` (`[\u3131-\u318E\uAC00-\uD7A3]+`)
- **중문(cn)**: `ml-cn` (`[\u4E00-\u9FBF]+`)
- **일문(jp)**: `ml-jp` (`[\u3040-\u309F\u30A0-\u30FF]+`)
- **숫자(num)**: `ml-num` (`[0-9]+`)
- **문장부호(punct)**: `ml-punct` (`[（）().#^\-&,;:@%*，、。」'"‘’“”«»–—…]+`)

클래스 접두사는 설정의 `class_prefix`를 따릅니다. 기본은 `ml-`입니다.

## 📘 사용법

### ⚡️ 1) 자동 적용(권장)

관리자 설정의 `자동 적용 셀렉터(auto_selectors)`에 원하는 셀렉터를 추가하세요.

예: `.project_text`

```html
<div class="project_text">Hello World 123! 안녕하세요</div>
```

위 요소 내부 텍스트에서 선택한 타입들이 `<span class="ml-en">`, `<span class="ml-num">` 등으로 래핑됩니다.

### 🖱️ 2) 수동 트리거(선택)

동적으로 HTML을 삽입한 직후 강제로 적용하고 싶다면:

```html
<script>
  // 문서 전체에 대해 다시 적용
  window.MLWP_APPLY(document);
  // 또는 특정 루트 요소만 대상으로 적용
  const root = document.querySelector('.project_text');
  window.MLWP_APPLY(root);
</script>
```

## 🚫 예외 처리와 제외 셀렉터

- 다음 태그 내부 텍스트는 래핑 대상에서 제외됩니다: `script`, `style`, `code`, `pre`, `textarea`, `kbd`, `samp`
- `exclude_selectors`에 지정한 영역(및 하위 요소)은 서버/클라이언트 모두에서 래핑하지 않습니다.
- 대괄호로 감싼 세그먼트(`[ ... ]`)는 서버사이드에서 보호되어 원문이 유지되도록 처리합니다.

## ⚠️ 성능 주의사항

- 서버사이드 처리는 `auto_selectors`에 포함된 클래스명이 실제 HTML에 존재하는 경우에만 시도합니다(빠른 탐색 후 조건부 적용).
- 복잡한 CSS 셀렉터는 서버사이드 XPath 변환 과정에서 일부 동작이 제한될 수 있습니다. 단순 셀렉터 사용을 권장합니다.

## 🎨 스타일링 예시

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
```

## ✨ 변경 사항 하이라이트(v1.1.0)

- MU 플러그인에서 일반 플러그인으로 전환(플러그인 활성화 필요)
- 관리자 설정 페이지 추가: 타입/프리픽스/자동 적용/제외/숏코드 화이트리스트
- 서버사이드 래핑 강화: 블록/본문/선택적 숏코드, AJAX/REST 응답 후처리
- 클라이언트 보조 스크립트 추가: 동적 DOM 반영(MutationObserver), 수동 트리거 `window.MLWP_APPLY`
- 예외 태그/제외 셀렉터/대괄호 보호 처리

## ✅ 호환성

이 플러그인은 [multilingual.js](https://github.com/multilingualjs/multilingual.js) 라이브러리의 패턴/클래스 네이밍을 참조합니다.

## 📄 라이선스 / 정보

- Author: Everyday Practice
- Plugin URI: https://everyday-practice.com
- Version: 1.1.0
