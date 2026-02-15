(function () {
  function ready(fn) {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", fn);
    } else {
      fn();
    }
  }

  function qs(root, sel) {
    return (root || document).querySelector(sel);
  }

  function getWindows() {
    var out = [];
    function add(w) {
      if (!w || out.indexOf(w) !== -1) return;
      out.push(w);
    }
    try { add(window.top); } catch (e) {}
    try { add(window.parent); } catch (e) {}
    add(window);
    return out;
  }

  function setStatus(box, message, isError) {
    var el = qs(box, "#ai-polish-status");
    if (!el) return;
    el.textContent = message || "";
    if (isError) el.classList.add("is-error");
    else el.classList.remove("is-error");
  }

  function getGutenbergField(name) {
    var wins = getWindows();
    for (var i = 0; i < wins.length; i += 1) {
      try {
        var w = wins[i];
        if (w.wp && w.wp.data && w.wp.data.select) {
          if (name === "content") return w.wp.data.select("core/editor").getEditedPostContent();
          return w.wp.data.select("core/editor").getEditedPostAttribute(name);
        }
      } catch (e) {}
    }
    return null;
  }

  function setGutenbergFields(fields) {
    var wins = getWindows();
    for (var i = 0; i < wins.length; i += 1) {
      try {
        var w = wins[i];
        if (w.wp && w.wp.data && w.wp.data.select && w.wp.data.dispatch) {
          var editorStore = w.wp.data.select("core/editor");
          var editorDispatch = w.wp.data.dispatch("core/editor");
          if (!editorStore || typeof editorStore.getEditedPostContent !== "function") continue;
          if (!editorDispatch || typeof editorDispatch.editPost !== "function") continue;
          var payload = {};
          if (typeof fields.content === "string") payload.content = fields.content;
          if (typeof fields.title === "string") payload.title = fields.title;
          if (typeof fields.excerpt === "string") payload.excerpt = fields.excerpt;
          editorDispatch.editPost(payload);
          return true;
        }
      } catch (e) {}
    }
    return false;
  }

  function getClassicField(id) {
    var wins = getWindows();
    var best = "";

    function pick(value) {
      var v = typeof value === "string" ? value : "";
      if (!v) return;
      if (v.length > best.length) best = v;
    }

    for (var i = 0; i < wins.length; i += 1) {
      var w = wins[i];
      try {
        if (id === "content" && w.tinymce && w.tinymce.get("content")) {
          pick(w.tinymce.get("content").getContent());
        }
      } catch (e) {}
      try {
        if (id === "content" && w.tinymce && Array.isArray(w.tinymce.editors)) {
          for (var ei = 0; ei < w.tinymce.editors.length; ei += 1) {
            var ed = w.tinymce.editors[ei];
            if (ed && typeof ed.getContent === "function") {
              pick(ed.getContent());
            }
          }
        }
      } catch (e6) {}
      try {
        if (id === "content" && w.tinymce && w.tinymce.activeEditor && typeof w.tinymce.activeEditor.getContent === "function") {
          pick(w.tinymce.activeEditor.getContent());
        }
      } catch (e4) {}
      try {
        if (id === "content" && w.wp && w.wp.oldEditor && typeof w.wp.oldEditor.getContent === "function") {
          pick(w.wp.oldEditor.getContent("content"));
        }
      } catch (e5) {}
      try {
        if (id === "content" && w.document) {
          var ifr = w.document.getElementById("content_ifr");
          if (ifr && ifr.contentDocument && ifr.contentDocument.body) {
            var html = ifr.contentDocument.body.innerHTML || "";
            if (html && html !== "<br data-mce-bogus=\"1\">") pick(html);
          }
        }
      } catch (e3) {}
      try {
        if (id === "content" && w.document) {
          var ifrList = w.document.querySelectorAll("iframe[id$='_ifr']");
          for (var fi = 0; fi < ifrList.length; fi += 1) {
            var iframe = ifrList[fi];
            if (iframe && iframe.contentDocument && iframe.contentDocument.body) {
              pick(iframe.contentDocument.body.innerHTML || "");
            }
          }
        }
      } catch (e7) {}
      try {
        if (w.document) {
          var el = w.document.getElementById(id) || w.document.querySelector("textarea.wp-editor-area");
          if (el) pick(el.value || "");
          if (id === "content") {
            if (w.wpActiveEditor) {
              var active = w.document.getElementById(String(w.wpActiveEditor));
              if (active) pick(active.value || "");
            }
            var list = w.document.querySelectorAll("textarea#content, textarea[name='content'], textarea.wp-editor-area");
            for (var j = 0; j < list.length; j += 1) {
              pick(list[j].value || "");
            }
          }
        }
      } catch (e2) {}
    }
    return best;
  }

  function setClassicFields(fields) {
    var wins = getWindows();
    var ok = false;
    for (var i = 0; i < wins.length; i += 1) {
      var w = wins[i];
      try {
        if (typeof fields.content === "string" && w.tinymce && w.tinymce.get("content")) {
          w.tinymce.get("content").setContent(fields.content);
          ok = true;
        }
      } catch (e) {}
      try {
        if (w.document) {
          if (typeof fields.content === "string") {
            var c = w.document.getElementById("content");
            if (c) { c.value = fields.content; ok = true; }
          }
          if (typeof fields.title === "string") {
            var t = w.document.getElementById("title");
            if (t) { t.value = fields.title; ok = true; }
          }
          if (typeof fields.excerpt === "string") {
            var e = w.document.getElementById("excerpt");
            if (e) { e.value = fields.excerpt; ok = true; }
          }
        }
      } catch (e2) {}
    }
    return ok;
  }

  function getEditorContent() {
    var content = getGutenbergField("content");
    if (typeof content === "string") return content;
    return getClassicField("content");
  }

  function getEditorTitle() {
    var title = getGutenbergField("title");
    if (typeof title === "string") return title;
    return getClassicField("title");
  }

  function getEditorExcerpt() {
    var excerpt = getGutenbergField("excerpt");
    if (typeof excerpt === "string") return excerpt;
    return getClassicField("excerpt");
  }

  function setEditorFields(fields) {
    fields = fields || {};
    if (setGutenbergFields(fields)) return true;
    return setClassicFields(fields);
  }

  function getAutoReplace(box) {
    return (box.getAttribute("data-auto-replace") || "") === "1";
  }

  function run(box) {
    var ajaxUrl = box.getAttribute("data-ajax-url") || window.ajaxurl || "";
    var nonce = box.getAttribute("data-nonce") || "";
    var postId = parseInt(box.getAttribute("data-post-id") || "0", 10);
    var autoReplace = getAutoReplace(box);
    if (!ajaxUrl || !nonce || !postId) {
      setStatus(box, "Missing AJAX config.", true);
      return;
    }

    var content = getEditorContent();
    if (!content || !content.trim()) {
      setStatus(box, "No live editor content found. Using saved post content...", false);
      content = "";
    }

    var actionType = (qs(box, "#ai-polish-action") || {}).value || "polish";
    var includeTitle = !!(qs(box, "#ai-polish-include-title") || {}).checked;
    var includeExcerpt = !!(qs(box, "#ai-polish-include-excerpt") || {}).checked;

    var clicks = parseInt(box.getAttribute("data-clicks") || "0", 10) || 0;
    clicks += 1;
    box.setAttribute("data-clicks", String(clicks));
    setStatus(box, "Running... (click " + clicks + ")", false);

    ajaxPost(ajaxUrl, {
      action: "ai_polish_rewrite",
      nonce: nonce,
      post_id: String(postId),
      action_type: actionType,
      model: "",
      content: content,
      include_title: includeTitle ? "1" : "0",
      include_excerpt: includeExcerpt ? "1" : "0",
      title: includeTitle ? getEditorTitle() : "",
      excerpt: includeExcerpt ? getEditorExcerpt() : "",
    }).then(function (res) {
      if (!res || !res.success) {
        var msg = (res && res.data && res.data.message) || "Request failed.";
        if (String(msg).trim() === "-1") msg = "Security check failed (nonce). Refresh and retry.";
        if (String(msg).trim() === "0") msg = "Permission denied.";
        setStatus(box, msg, true);
        return;
      }

      var data = res.data || {};
      var outContent = qs(box, "#ai-polish-output");
      var outTitle = qs(box, "#ai-polish-output-title");
      var outExcerpt = qs(box, "#ai-polish-output-excerpt");
      if (outContent) outContent.value = data.content || "";
      if (includeTitle && outTitle) outTitle.value = data.title || "";
      if (includeExcerpt && outExcerpt) outExcerpt.value = data.excerpt || "";

      if (autoReplace) {
        var fields = { content: data.content || "" };
        if (includeTitle && typeof data.title === "string" && data.title.trim()) fields.title = data.title;
        if (includeExcerpt && typeof data.excerpt === "string" && data.excerpt.trim()) fields.excerpt = data.excerpt;
        if (setEditorFields(fields)) {
          setStatus(box, "Done (replaced).", false);
        } else {
          setStatus(box, "Done. Could not replace editor content.", true);
        }
        return;
      }
      setStatus(box, "Done.", false);
    });
  }

  function ajaxPost(url, params) {
    var body = new URLSearchParams();
    Object.keys(params || {}).forEach(function (k) {
      body.set(k, params[k]);
    });

    return fetch(url, {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
      body: body.toString(),
    })
      .then(function (r) {
        return r.text();
      })
      .then(function (text) {
        try {
          return JSON.parse(text);
        } catch (e) {
          return { success: false, data: { message: text || "Invalid response." } };
        }
      });
  }

  function replace(box) {
    var content = ((qs(box, "#ai-polish-output") || {}).value || "").trim();
    if (!content) {
      setStatus(box, "Nothing to replace with.", true);
      return;
    }

    var includeTitle = !!(qs(box, "#ai-polish-include-title") || {}).checked;
    var includeExcerpt = !!(qs(box, "#ai-polish-include-excerpt") || {}).checked;

    var fields = { content: content };
    if (includeTitle) {
      var title = ((qs(box, "#ai-polish-output-title") || {}).value || "").trim();
      if (title) fields.title = title;
    }
    if (includeExcerpt) {
      var excerpt = ((qs(box, "#ai-polish-output-excerpt") || {}).value || "").trim();
      if (excerpt) fields.excerpt = excerpt;
    }

    if (setEditorFields(fields)) setStatus(box, "Replaced.", false);
    else setStatus(box, "Could not update editor.", true);
  }

  function onClickCapture(ev) {
    try {
      var t = ev.target;
      if (!t || !t.closest) return;

      var runEl = t.closest(".ai-polish-metabox #ai-polish-run");
      if (runEl) {
        ev.preventDefault();
        if (typeof ev.stopImmediatePropagation === "function") ev.stopImmediatePropagation();
        if (typeof ev.stopPropagation === "function") ev.stopPropagation();
        var box = runEl.closest(".ai-polish-metabox");
        if (box) {
          var c = qs(box, "#ai-polish-clicks");
          if (c) {
            var n = parseInt(c.textContent || "0", 10) || 0;
            n += 1;
            c.textContent = String(n);
            c.classList.add("is-on");
            setTimeout(function () {
              c.classList.remove("is-on");
            }, 350);
          }
          run(box);
        }
        return;
      }

      var repEl = t.closest(".ai-polish-metabox #ai-polish-replace");
      if (repEl) {
        ev.preventDefault();
        if (typeof ev.stopImmediatePropagation === "function") ev.stopImmediatePropagation();
        if (typeof ev.stopPropagation === "function") ev.stopPropagation();
        var box2 = repEl.closest(".ai-polish-metabox");
        if (box2) replace(box2);
      }
    } catch (e) {
      try {
        var boxAny = document.querySelector(".ai-polish-metabox");
        if (boxAny) setStatus(boxAny, "JS error: " + (e && e.message ? e.message : "unknown"), true);
      } catch (e2) {}
    }
  }

  ready(function () {
    // Bind early in capture so other admin JS can't swallow the event.
    window.addEventListener("click", onClickCapture, true);
    document.addEventListener("click", onClickCapture, true);
    document.querySelectorAll(".ai-polish-metabox").forEach(function (box) {
      var build = box.getAttribute("data-build") || "";
      var flag = qs(box, "#ai-polish-script-status");
      if (flag) flag.textContent = "loaded";
      setStatus(box, build ? "Ready (build " + build + "). Click Run." : "Ready. Click Run.", false);
    });
  });

  // Expose direct handlers so inline fallback hooks can invoke run/replace
  // even if delegated click listeners are interrupted by other admin scripts.
  window.aiPolishFallbackRun = function (button) {
    var box = button && button.closest ? button.closest(".ai-polish-metabox") : document.querySelector(".ai-polish-metabox");
    if (box) run(box);
  };

  window.aiPolishFallbackReplace = function (button) {
    var box = button && button.closest ? button.closest(".ai-polish-metabox") : document.querySelector(".ai-polish-metabox");
    if (box) replace(box);
  };
})();
