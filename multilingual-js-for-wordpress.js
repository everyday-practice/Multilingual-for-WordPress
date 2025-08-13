(function () {
  function wrapText(text, types, classPrefix) {
    if (!text || typeof text !== 'string') return text;
    const patternSources = {
      en: '[A-Za-z]+',
      ko: '[\u3131-\u318E\uAC00-\uD7A3]+',
      cn: '[\u4E00-\u9FBF]+',
      jp: '[\u3040-\u309F\u30A0-\u30FF]+',
      num: '[0-9]+',
      // < > 제거, 인용부호/대시/ellipsis 추가
      punct: '[（）().#^\\-&,;:@%*，、。」\'"‘’“”«»–—…]+',
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
    return text.replace(regex, function () {
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
  }

  // Helper: check if an element is inside any excluded selector
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

  function init() {
    apply(document);

    // Minimal observers for dynamic DOM updates (Interactivity API, etc.)
    try {
      const cfg = window.MLWP_CFG || {};
      const selectors = cfg.selectors || [];
      if (selectors.length) {
        const observer = new MutationObserver(function (mutations) {
          const roots = [];
          mutations.forEach(function (m) {
            // Handle added elements
            m.addedNodes &&
              Array.prototype.forEach.call(m.addedNodes, function (node) {
                if (!node || node.nodeType !== 1) return; // element only
                // If the added node itself matches any selector, include it
                for (let i = 0; i < selectors.length; i++) {
                  const sel = selectors[i];
                  if (node.matches && node.matches(sel) && !isWithinExcluded(node, cfg.excludeSelectors || [])) {
                    roots.push(node);
                    break;
                  }
                }
                // Also check descendants
                selectors.forEach(function (sel) {
                  node.querySelectorAll &&
                    node.querySelectorAll(sel).forEach(function (el) {
                      if (!isWithinExcluded(el, cfg.excludeSelectors || [])) roots.push(el);
                    });
                });
              });

            // Handle pure text updates (Interactivity API, etc.)
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
            // De-duplicate
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
      // noop
    }
  }

  // Expose a light-weight manual trigger
  window.MLWP_APPLY = apply;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
