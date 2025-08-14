/**
 * Multilingual.js for WordPress 프론트엔드 스크립트
 * - window.MLWP_CFG 설정(types, classPrefix, selectors, excludeSelectors)을 사용합니다.
 * - 선택자 범위 내 텍스트 노드에서 언어/숫자/문장부호 토큰을 찾아 span 으로 감쌉니다.
 * - 초기 렌더와 동적 DOM 변경(MutationObserver) 모두 처리합니다.
 */
(function () {
  /**
   * 텍스트 내 지정된 유형(en, ko, cn, jp, num, punct)을 찾아
   * classPrefix-<type> 클래스를 가진 span 으로 감쌉니다.
   * @param {string} text
   * @param {string[]} types
   * @param {string} classPrefix
   * @returns {string}
   */
  function wrapText(text, types, classPrefix) {
    if (!text || typeof text !== 'string') return text;
    const patternSources = {
      en: '[A-Za-z]+',
      ko: '[\u3131-\u318E\uAC00-\uD7A3]+',
      cn: '[\u4E00-\u9FBF]+',
      jp: '[\u3040-\u309F\u30A0-\u30FF]+',
      num: '[0-9]+',
      punct: '[\\[\\]（）().#^\\-&,;:@%*，、。」\'"‘’“”«»–—…]+',
    };
    const parts = [];
    const indexToType = [];
    types.forEach(function (t) {
      if (patternSources[t]) {
        parts.push('(' + patternSources[t] + ')');
        indexToType.push(t);
      }
    });
    if (!parts.length) return text;
    const regex = new RegExp(parts.join('|'), 'gu');

    // {{ ... }} 보호 세그먼트를 분리하여 해당 부분은 래핑 제외
    const protectedRegex = /\{\{[^}]+\}\}/g;
    const doReplace = function (s) {
      return s.replace(regex, function () {
        const args = Array.prototype.slice.call(arguments);
        const match = args[0];
        const groups = args.slice(1, 1 + parts.length);
        let tp = null;
        for (let i = 0; i < groups.length; i++) {
          if (groups[i] !== undefined) {
            tp = indexToType[i];
            break;
          }
        }
        if (!tp) return match;
        return '<span class="' + classPrefix + '-' + tp + '">' + match + '</span>';
      });
    };

    let result = '';
    let lastIndex = 0;
    let m;
    while ((m = protectedRegex.exec(text)) !== null) {
      const before = text.slice(lastIndex, m.index);
      if (before) {
        result += doReplace(before);
      }
      result += m[0]; // 보호 구간은 원문 유지
      lastIndex = m.index + m[0].length;
    }
    if (lastIndex < text.length) {
      result += doReplace(text.slice(lastIndex));
    }
    return result || text;
  }

  // 요소가 제외 선택자 내부인지 여부
  function isWithinExcluded(el, excludeSelectors) {
    if (!excludeSelectors || !excludeSelectors.length) return false;
    for (let n = el; n && n.nodeType === 1; n = n.parentNode) {
      for (let i = 0; i < excludeSelectors.length; i++) {
        const sel = excludeSelectors[i];
        try {
          if (n.matches && n.matches(sel)) return true;
        } catch (e) {}
      }
    }
    return false;
  }

  // 요소 하위 텍스트 노드를 순회하며 래핑 적용(제외 태그/이미 래핑 span/공백 노드 건너뜀)
  function processElement(element, types, classPrefix) {
    const walker = document.createTreeWalker(
      element,
      NodeFilter.SHOW_TEXT,
      {
        acceptNode: function (node) {
          const parent = node.parentNode;
          if (!parent || parent.nodeType !== Node.ELEMENT_NODE) return NodeFilter.FILTER_REJECT;
          const tag = parent.tagName.toLowerCase();
          if (['script', 'style', 'code', 'pre', 'textarea', 'kbd', 'samp'].includes(tag))
            return NodeFilter.FILTER_REJECT;
          if (
            tag === 'span' &&
            parent.className &&
            parent.className.indexOf(
              (window.MLWP_CFG && window.MLWP_CFG.classPrefix ? window.MLWP_CFG.classPrefix : 'ml') + '-'
            ) === 0
          )
            return NodeFilter.FILTER_REJECT;
          const cfg = window.MLWP_CFG || {};
          if (isWithinExcluded(parent, cfg.excludeSelectors || [])) return NodeFilter.FILTER_REJECT;
          if (!node.nodeValue || !node.nodeValue.trim()) return NodeFilter.FILTER_REJECT;
          return NodeFilter.FILTER_ACCEPT;
        },
      },
      false
    );
    const targets = [];
    let n;
    while ((n = walker.nextNode())) targets.push(n);
    targets.forEach(function (textNode) {
      const original = textNode.nodeValue;
      const cfg = window.MLWP_CFG || {};
      const wrapped = wrapText(original, cfg.types || [], cfg.classPrefix || 'ml');
      if (wrapped === original) return;
      const temp = document.createElement('div');
      temp.innerHTML = wrapped;
      const parent = textNode.parentNode;
      while (temp.firstChild) parent.insertBefore(temp.firstChild, textNode);
      parent.removeChild(textNode);
    });
  }

  // 설정된 선택자에 대해 루트 기준으로 래핑 적용
  function apply(root) {
    if (!window.MLWP_CFG) return;
    const cfg = window.MLWP_CFG;
    (cfg.selectors || []).forEach(function (selector) {
      (root || document).querySelectorAll(selector).forEach(function (el) {
        if (isWithinExcluded(el, cfg.excludeSelectors || [])) return;
        processElement(el, cfg.types || [], cfg.classPrefix || 'ml');
      });
    });
  }

  // 초기화: 최초 적용 + 동적 DOM 대응 옵저버 등록
  function init() {
    apply(document);

    // 동적 DOM 업데이트(Interactivity API 등)를 위한 최소한의 옵저버
    try {
      const cfg = window.MLWP_CFG || {};
      const selectors = cfg.selectors || [];
      if (selectors.length) {
        const observer = new MutationObserver(function (mutations) {
          const roots = [];
          mutations.forEach(function (m) {
            // 추가된 요소
            m.addedNodes &&
              Array.prototype.forEach.call(m.addedNodes, function (node) {
                if (!node || node.nodeType !== 1) return; // 요소 노드만
                // 자체 매칭 시 루트 추가
                for (let i = 0; i < selectors.length; i++) {
                  const sel = selectors[i];
                  if (node.matches && node.matches(sel) && !isWithinExcluded(node, cfg.excludeSelectors || [])) {
                    roots.push(node);
                    break;
                  }
                }
                // 하위 검사
                selectors.forEach(function (sel) {
                  node.querySelectorAll &&
                    node.querySelectorAll(sel).forEach(function (el) {
                      if (!isWithinExcluded(el, cfg.excludeSelectors || [])) roots.push(el);
                    });
                });
              });

            // 텍스트 변경
            if (m.type === 'characterData' && m.target && m.target.parentNode) {
              var el = m.target.parentNode;
              for (var i = 0; i < selectors.length; i++) {
                var root = el.closest && el.closest(selectors[i]);
                if (root && !isWithinExcluded(root, cfg.excludeSelectors || [])) {
                  roots.push(root);
                  break;
                }
              }
            }
          });
          if (roots.length) {
            // 중복 제거
            const seen = new Set();
            roots.forEach(function (r) {
              if (seen.has(r)) return;
              seen.add(r);
              processElement(r, cfg.types || [], cfg.classPrefix || 'ml');
            });
          }
        });
        observer.observe(document.documentElement || document.body, {
          childList: true,
          subtree: true,
          characterData: true,
        });
      }
    } catch (e) {
      // 무시
    }
  }

  // 수동 트리거
  window.MLWP_APPLY = apply;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
