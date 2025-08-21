# 🌐 Multilingual for WordPress

[![WordPress](https://img.shields.io/badge/WordPress-6.x%2B-21759B?logo=wordpress&logoColor=white)](#) [![Type](https://img.shields.io/badge/Type-Plugin-blue)](#) [![Multilingual](https://img.shields.io/badge/Multilingual-i18n%2Fl10n-4CAF50)](#) [![Typography](https://img.shields.io/badge/Typography-Text%20wrapping-8E44AD)](#) [![Performance](https://img.shields.io/badge/Performance-Optimized-FF9800)](#) [![Accessibility](https://img.shields.io/badge/Accessibility-a11y-795548)](#)

어도비 인디자인의 합성글꼴(섞어짜기)처럼 보다 섬세하게 다국어 섞어쓰기를 제어하는 위한 플러그인으로 오픈소스 라이브러리 multilingual.js를 워드프레스에 맞게 재구현했습니다.
서버사이드에서 텍스트(영문/숫자/문장부호/한글/중문/일문 등)를 유형별로 `<span>`으로 래핑하여 스타일 지연 현상(FOUC)을 방지합니다.
AJAX / Interactivity API 에도 대응하여 클라이언트 사이드 랜더링 후 동적으로 업데이트되는 DOM에도 대응합니다.

## 📋 요구 사항

- WordPress 6.x 이상

## 🛠️ 설치

1. 폴더를 `wp-content/plugins/multilingual-js-for-wordpress/`에 업로드
2. 관리자에서 플러그인을 활성화: `플러그인 → 설치된 플러그인 → Multilingual for WordPress → 활성화`

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
- **커스텀 문자세트(custom_charsets)**: 사용자 정의 문자 타입과 패턴 추가
  - 한 줄에 하나씩 `타입명:문자세트` 형식으로 입력
  - 예: `bullet:•`, `arrow:→←↑↓`

## 🧠 동작 개요

- 프론트엔드에서 플러그인 스크립트를 등록하고, 설정을 `window.MLWP_CFG`로 인라인 주입합니다.
- 서버사이드 래핑 적용 지점: `render_block`, `the_content`, 선택적 `do_shortcode_tag`.
- 클라이언트 사이드 스크립트는 `MutationObserver`로 동적 DOM 변화를 감지하며 `auto_selectors` 대상에 보조 래핑을 수행합니다.
- AJAX/REST 및 에디터/인터랙션 환경에서의 예외와 세부 동작은 아래 Interactivity 섹션을 참고하세요.

## 🧩 Interactivity API 및 최신 변경 사항 대응

- DOM 업데이트 추적: `MutationObserver`로 자동 감지, 필요 시 `window.MLWP_APPLY(root)`로 수동 재적용.
- 서버사이드 적용/예외:
  - `render_block`: 프론트 요청에서만 적용, 관리자/REST 블록 렌더러 제외.
  - `the_content`: 본문 출력 단계 적용.
  - `do_shortcode_tag`: `shortcode_whitelist`에 등록된 항목에만 적용.
  - `admin-ajax.php`: 비관리자 AJAX 응답을 버퍼링해 JSON/HTML에서 HTML을 조건부 래핑. JSON은 `template/html/content/rendered/markup/output` 키 우선 탐색.
  - REST `rest_post_dispatch`: `context=edit` 또는 `/wp/v2/block-renderer` 제외. 데이터 전체를 순회하며 위와 같은 키에서 HTML을 조건부 래핑.
- 호환되는 AJAX 기반 플러그인: FacetWP, Search & Filter, Ajax Load More 등 대부분의 플러그인이 `admin-ajax.php` 또는 REST로 HTML/JSON을 반환하는 경우 자동 래핑이 적용됩니다. 플러그인 템플릿 구조에 따라 `예외 셀렉터`를 조정하는 것을 권장합니다.
- 제외/보호:
  - `예외 셀렉터`로 지정한 영역(하위 포함)은 서버/클라이언트 모두 래핑하지 않습니다.
  - 보호 토큰: `{{ ... }}` 및 화이트리스트된 숏코드 본문은 원문 유지.

## 🔤 지원 타입과 클래스

- **영문(en)**: `ml-en` (`[A-Za-z]+`)
- **한글(ko)**: `ml-ko` (`[\u3131-\u318E\uAC00-\uD7A3]+`)
- **중문(cn)**: `ml-cn` (`[\u4E00-\u9FBF]+`)
- **일문(jp)**: `ml-jp` (`[\u3040-\u309F\u30A0-\u30FF]+`)
- **숫자(num)**: `ml-num` (`[0-9]+`)
- **문장부호(punct)**: `ml-punct` (`[（）().#^\-&,;:@%*，、。」'"''""«»–—…]+`)

### 커스텀 문자세트

설정에서 추가할 수 있는 사용자 정의 타입입니다. 다음과 같은 형식으로 입력합니다:

```
parentheses:(){}[]
bullet:•◦▪▫
arrow:→←↑↓
emoji:😀😃��
currency:$€¥₩
```

## 📘 사용법

### ⚡️ 1) 자동 적용(권장)

WordPress 관리자 → 플러그인 설정에서 **자동 적용 셀렉터(Auto Selectors)**에 원하는 CSS 선택자를 추가하세요.

**자동 적용 셀렉터 설정 예시**:

- `.project_text` (특정 클래스)
- `p` (모든 문단)
- `article` (아티클 요소)
- `.content, .entry-content` (여러 선택자)

```html
<div class="project_text">Hello World 123! 안녕하세요</div>
```

위 요소 내부 텍스트에서 선택한 타입들이 `<span class="ml-en">`, `<span class="ml-num">` 등으로 래핑됩니다.

### 🖱️ 2) 수동 트리거(선택)

동적으로 HTML을 삽입한 직후 강제로 적용하고 싶다면:

```html
<script>
  // 전체 문서에 대해 적용 (권장)
  window.MLWP_APPLY(document);
</script>
```

**⚠️ 중요**: `window.MLWP_APPLY()` 함수는 WordPress 관리자에서 설정한 **자동 적용 셀렉터**에 해당하는 요소들에만 적용됩니다.

- 특정 요소만 대상으로 하려면, 해당 선택자가 자동 적용 셀렉터에 등록되어 있어야 합니다.
- 자동 적용 셀렉터가 비어있으면 `window.MLWP_APPLY()`는 아무 작업도 수행하지 않습니다.

**동작 방식 이해**:

`window.MLWP_APPLY(root)`는 **root 내부에서** 자동 적용 셀렉터에 등록된 선택자들을 찾습니다.

```javascript
// 자동 적용 셀렉터에 '.project_text'가 등록되어 있는 경우:

// ✅ 작동함 - 전체 문서에서 .project_text 요소들을 찾아 적용
window.MLWP_APPLY(document);
// → document.querySelectorAll('.project_text')

// ❌ 작동하지 않는 경우
const root = document.querySelector('.project_text');
window.MLWP_APPLY(root);
// → root.querySelectorAll('.project_text')
// .project_text 요소 내부에는 또 다른 .project_text가 없음!

// ✅ 올바른 사용법
const container = document.querySelector('.container');
window.MLWP_APPLY(container);
// → container 내부에 있는 .project_text 요소들을 찾아 적용
```

## 🚫 예외 처리와 제외 셀렉터

- 다음 태그 내부 텍스트는 래핑 대상에서 제외됩니다: `script`, `style`, `code`, `pre`, `textarea`, `kbd`, `samp`
- `예외 셀렉터`에 지정한 영역(및 하위 요소)은 서버/클라이언트 모두에서 래핑하지 않습니다.
- 대괄호로 감싼 세그먼트(`[ ... ]`)는 서버사이드에서 보호되어 원문이 유지되도록 처리합니다.

## ⚠️ 성능 주의사항

- 서버사이드 처리는 `auto_selectors`의 클래스 토큰이 실제 HTML에 존재할 때만 실행됩니다.
- 복잡한 CSS 셀렉터는 XPath 변환 제약이 있어 단순 셀렉터 사용을 권장합니다.

## 🎨 스타일링 예시

```css
/* 예시 */
body {
  font-family: 'Roboto', 'Noto Sans KR', sans-serif;
  font-size: 1rem;
  line-height: 1.6;
}

.ml-en,
.ml-num,
.ml-punct {
  position: relative;
  font-size: 94%;
  top: -0.01em;
}
```

## ✅ 호환성

이 플러그인은 [multilingual.js](https://github.com/multilingualjs/multilingual.js) 라이브러리의 패턴/클래스 네이밍을 참조합니다.

## 📄 라이선스 / 정보

- Author: Everyday Practice
- Plugin URI: https://everyday-practice.com
- Version: 1.1.0
