(function ($) {
  // Hard fallback: if jQuery isn't available/working (rare, but can happen with admin optimizers),
  // bind with vanilla DOM + fetch so the Run button never silently does nothing.
  var hasJQ = !!$ && typeof $.ajax === "function";

  function domReady(fn) {
    if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", fn);
    else fn();
  }

  function setStatusEl(el, message, isError) {
    if (!el) return;
    el.textContent = message || "";
    if (isError) el.classList.add("is-error");
    else el.classList.remove("is-error");
  }

  function closestMetabox(target) {
    if (!target || !target.closest) return null;
    return target.closest(".ai-polish-metabox");
  }

  function getEditorField(name) {
    try {
      if (window.wp && wp.data && wp.data.select) {
        if (name === "content") return wp.data.select("core/editor").getEditedPostContent();
        return wp.data.select("core/editor").getEditedPostAttribute(name);
      }
    } catch (e) {}

    if (name === "content") {
      if (window.tinymce && tinymce.get("content")) return tinymce.get("content").getContent();
      var ta = document.getElementById("content");
      return ta ? ta.value : "";
    }

    var el = document.getElementById(name === "title" ? "title" : "excerpt");
    return el ? el.value : "";
  }

  function setEditorFieldsVanilla(fields) {
    fields = fields || {};
    try {
      if (window.wp && wp.data && wp.data.dispatch) {
        var update = {};
        if (typeof fields.content === "string") update.content = fields.content;
        if (typeof fields.title === "string") update.title = fields.title;
        if (typeof fields.excerpt === "string") update.excerpt = fields.excerpt;
        wp.data.dispatch("core/editor").editPost(update);
        return true;
      }
    } catch (e) {}

    var ok = false;

    if (typeof fields.content === "string") {
      if (window.tinymce && tinymce.get("content")) {
        tinymce.get("content").setContent(fields.content);
        ok = true;
      } else {
        var ta = document.getElementById("content");
        if (ta) {
          ta.value = fields.content;
          ok = true;
        }
      }
    }

    if (typeof fields.title === "string") {
      var titleEl = document.getElementById("title");
      if (titleEl) {
        titleEl.value = fields.title;
        ok = true;
      }
    }

    if (typeof fields.excerpt === "string") {
      var excerptEl = document.getElementById("excerpt");
      if (excerptEl) {
        excerptEl.value = fields.excerpt;
        ok = true;
      }
    }

    return ok;
  }

  function postAjaxVanilla(action, data) {
    var params = new URLSearchParams();
    params.set("action", action);
    params.set("nonce", (window.aiPolish && aiPolish.nonce) || "");
    Object.keys(data || {}).forEach(function (k) {
      params.set(k, data[k]);
    });

    return fetch((window.aiPolish && aiPolish.ajaxUrl) || "", {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
      body: params.toString(),
    }).then(function (r) {
      return r.text();
    }).then(function (text) {
      try {
        return JSON.parse(text);
      } catch (e) {
        var err = new Error("Non-JSON response");
        err.raw = text;
        throw err;
      }
    });
  }

  if (!hasJQ) {
    domReady(function () {
      document.querySelectorAll(".ai-polish-metabox #ai-polish-status").forEach(function (el) {
        if (!el.textContent.trim()) setStatusEl(el, "AI Polish ready.", false);
      });
    });

    document.addEventListener(
      "click",
      function (ev) {
        var t = ev.target;
        if (!t) return;

        var isRun = t.id === "ai-polish-run";
        var isReplace = t.id === "ai-polish-replace";
        if (!isRun && !isReplace) return;

        var box = closestMetabox(t);
        if (!box) return;

        var statusEl = box.querySelector("#ai-polish-status");
        if (!window.aiPolish || !aiPolish.ajaxUrl || !aiPolish.nonce) {
          setStatusEl(statusEl, "AI Polish is not initialized (missing AJAX config).", true);
          return;
        }

        if (isReplace) {
          var out = (box.querySelector("#ai-polish-output") || {}).value || "";
          if (!out.trim()) {
            setStatusEl(statusEl, "Nothing to replace with.", true);
            return;
          }

          var fields = { content: out };
          var includeTitle = (box.querySelector("#ai-polish-include-title") || {}).checked;
          var includeExcerpt = (box.querySelector("#ai-polish-include-excerpt") || {}).checked;
          if (includeTitle) {
            var outTitle = ((box.querySelector("#ai-polish-output-title") || {}).value || "").trim();
            if (outTitle) fields.title = outTitle;
          }
          if (includeExcerpt) {
            var outExcerpt = ((box.querySelector("#ai-polish-output-excerpt") || {}).value || "").trim();
            if (outExcerpt) fields.excerpt = outExcerpt;
          }

          if (setEditorFieldsVanilla(fields)) setStatusEl(statusEl, "Replaced.", false);
          else setStatusEl(statusEl, "Could not update editor fields.", true);
          return;
        }

        // Run
        var postId = parseInt(box.getAttribute("data-post-id") || "0", 10);
        if (!postId) {
          setStatusEl(statusEl, "Missing post ID.", true);
          return;
        }

        setStatusEl(statusEl, "Running…", false);

        var includeTitle = (box.querySelector("#ai-polish-include-title") || {}).checked;
        var includeExcerpt = (box.querySelector("#ai-polish-include-excerpt") || {}).checked;
        var payload = {
          post_id: postId,
          action_type: (box.querySelector("#ai-polish-action") || {}).value || "polish",
          model: (window.aiPolish && aiPolish.settings && aiPolish.settings.model) || "",
          content: getEditorField("content") || "",
          include_title: includeTitle ? 1 : 0,
          include_excerpt: includeExcerpt ? 1 : 0,
          title: includeTitle ? getEditorField("title") || "" : "",
          excerpt: includeExcerpt ? getEditorField("excerpt") || "" : "",
        };

        if (!payload.content.trim()) {
          setStatusEl(statusEl, "No content found.", true);
          return;
        }

        postAjaxVanilla("ai_polish_rewrite", payload)
          .then(function (res) {
            if (!res || !res.success) {
              setStatusEl(statusEl, (res && res.data && res.data.message) || "Request failed.", true);
              return;
            }
            var out = (res.data && res.data.content) || "";
            var outEl = box.querySelector("#ai-polish-output");
            if (outEl) outEl.value = out;
            if (includeTitle) {
              var tEl = box.querySelector("#ai-polish-output-title");
              if (tEl) tEl.value = (res.data && res.data.title) || "";
            }
            if (includeExcerpt) {
              var eEl = box.querySelector("#ai-polish-output-excerpt");
              if (eEl) eEl.value = (res.data && res.data.excerpt) || "";
            }
            setStatusEl(statusEl, "Done.", false);

            if (window.aiPolish && aiPolish.settings && aiPolish.settings.auto_replace) {
              var fields = { content: out };
              if (includeTitle && res.data && typeof res.data.title === "string" && res.data.title.trim()) fields.title = res.data.title;
              if (includeExcerpt && res.data && typeof res.data.excerpt === "string" && res.data.excerpt.trim()) fields.excerpt = res.data.excerpt;
              if (setEditorFieldsVanilla(fields)) setStatusEl(statusEl, "Done (replaced).", false);
            }
          })
          .catch(function (err) {
            var raw = err && typeof err.raw === "string" ? err.raw.trim() : "";
            var msg =
              raw === "-1"
                ? "Security check failed (nonce). Refresh the page and try again."
                : raw === "0"
                ? "Permission denied."
                : "Request failed. Try refreshing the page.";
            setStatusEl(statusEl, msg, true);
          });
      },
      true
    );

    return;
  }
  function setStatus($el, message, isError) {
    $el.text(message || "");
    $el.toggleClass("is-error", !!isError);
  }

  function ajax(action, data) {
    return $.ajax({
      url: aiPolish.ajaxUrl,
      method: "POST",
      dataType: "json",
      data: Object.assign(
        {
          action: action,
          nonce: aiPolish.nonce,
        },
        data || {}
      ),
    });
  }

  function getEditorContent() {
    // Gutenberg
    try {
      if (window.wp && wp.data && wp.data.select) {
        var content = wp.data.select("core/editor").getEditedPostContent();
        if (typeof content === "string") return content;
      }
    } catch (e) {}

    // Classic editor fallback
    if (window.tinymce && tinymce.get("content")) {
      return tinymce.get("content").getContent();
    }
    var $textarea = $("#content");
    if ($textarea.length) return $textarea.val();
    return "";
  }

  function getEditorTitle() {
    // Gutenberg
    try {
      if (window.wp && wp.data && wp.data.select) {
        var title = wp.data.select("core/editor").getEditedPostAttribute("title");
        if (typeof title === "string") return title;
      }
    } catch (e) {}

    // Classic editor fallback
    var $title = $("#title");
    if ($title.length) return $title.val();
    return "";
  }

  function getEditorExcerpt() {
    // Gutenberg
    try {
      if (window.wp && wp.data && wp.data.select) {
        var excerpt = wp.data.select("core/editor").getEditedPostAttribute("excerpt");
        if (typeof excerpt === "string") return excerpt;
      }
    } catch (e) {}

    // Classic editor fallback
    var $excerpt = $("#excerpt");
    if ($excerpt.length) return $excerpt.val();
    return "";
  }

  function setEditorContent(html) {
    // Gutenberg
    try {
      if (window.wp && wp.data && wp.data.dispatch) {
        wp.data.dispatch("core/editor").editPost({ content: html });
        return true;
      }
    } catch (e) {}

    // Classic editor fallback
    if (window.tinymce && tinymce.get("content")) {
      tinymce.get("content").setContent(html);
      return true;
    }
    var $textarea = $("#content");
    if ($textarea.length) {
      $textarea.val(html);
      return true;
    }
    return false;
  }

  function setEditorFields(fields) {
    fields = fields || {};

    // Gutenberg
    try {
      if (window.wp && wp.data && wp.data.dispatch) {
        var update = {};
        if (typeof fields.content === "string") update.content = fields.content;
        if (typeof fields.title === "string") update.title = fields.title;
        if (typeof fields.excerpt === "string") update.excerpt = fields.excerpt;
        wp.data.dispatch("core/editor").editPost(update);
        return true;
      }
    } catch (e) {}

    // Classic editor fallback
    var ok = false;
    if (typeof fields.content === "string") ok = setEditorContent(fields.content) || ok;

    if (typeof fields.title === "string") {
      var $title = $("#title");
      if ($title.length) {
        $title.val(fields.title);
        ok = true;
      }
    }

    if (typeof fields.excerpt === "string") {
      var $excerpt = $("#excerpt");
      if ($excerpt.length) {
        $excerpt.val(fields.excerpt);
        ok = true;
      }
    }

    return ok;
  }

  function initSettings() {
    var $status = $("#ai-polish-settings-status");
    var $test = $("#ai-polish-test-connection");
    var $loadModels = $("#ai-polish-load-models");
    var $modelSelect = $("#ai-polish-model-select");
    var $modelFilter = $("#ai-polish-model-filter");
    var allModels = [];

    function getSelectedModel() {
      return $modelSelect.val() || "";
    }

    function renderModels(filterText) {
      var filter = (filterText || "").toLowerCase().trim();
      var selected = getSelectedModel() || (aiPolish.settings && aiPolish.settings.model) || "";

      $modelSelect.empty();
      $modelSelect.append($("<option />").attr("value", "").text("(Select a model)"));

      var models = Array.isArray(allModels) ? allModels : [];
      if (!models.length && selected) models = [selected];

      var shown = 0;
      models.forEach(function (id) {
        if (!id) return;
        var hay = String(id).toLowerCase();
        if (filter && hay.indexOf(filter) === -1) return;
        var $opt = $("<option />").attr("value", id).text(id);
        if (selected && selected === id) $opt.prop("selected", true);
        $modelSelect.append($opt);
        shown += 1;
      });

      if (selected && !$modelSelect.val()) {
        $modelSelect.prepend($("<option />").attr("value", selected).text(selected).prop("selected", true));
      }

      if (models.length && shown === 0) {
        $modelSelect.append($("<option />").attr("value", "").text("(No matches)"));
      }
    }

    // Initial render (in case there's a saved model).
    var existing = $modelSelect.val() || (aiPolish.settings && aiPolish.settings.model) || "";
    if (existing) allModels = [existing];
    renderModels("");

    $test.on("click", function () {
      setStatus($status, aiPolish.strings.testing, false);
      ajax("ai_polish_test_connection", {})
        .done(function (res) {
          if (res && res.success) setStatus($status, aiPolish.strings.testOk, false);
          else setStatus($status, (res && res.data && res.data.message) || aiPolish.strings.testFail, true);
        })
        .fail(function (xhr) {
          var raw = (xhr && typeof xhr.responseText === "string" && xhr.responseText.trim()) || "";
          var msg =
            (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ||
            (raw === "-1" ? "Security check failed (nonce). Refresh the page and try again." : "") ||
            (raw === "0" ? "Permission denied." : "") ||
            aiPolish.strings.testFail;
          setStatus($status, msg, true);
        });
    });

    $loadModels.on("click", function () {
      setStatus($status, aiPolish.strings.loading, false);
      ajax("ai_polish_fetch_models", {})
        .done(function (res) {
          if (!res || !res.success) {
            setStatus($status, (res && res.data && res.data.message) || "Failed to load models.", true);
            return;
          }

          allModels = (res.data && res.data.models) || [];
          renderModels($modelFilter.val() || "");
          setStatus($status, aiPolish.strings.modelsLoaded, false);
        })
        .fail(function (xhr) {
          var raw = (xhr && typeof xhr.responseText === "string" && xhr.responseText.trim()) || "";
          var msg =
            (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ||
            (raw === "-1" ? "Security check failed (nonce). Refresh the page and try again." : "") ||
            (raw === "0" ? "Permission denied." : "") ||
            "Failed to load models.";
          setStatus($status, msg, true);
        });
    });

    $modelFilter.on("input", function () {
      renderModels($modelFilter.val() || "");
    });
  }

  function initMetabox() {
    // If metabox exists but localization is missing, surface it in the UI.
    if (!window.aiPolish || !aiPolish.ajaxUrl || !aiPolish.nonce) {
      $(".ai-polish-metabox").each(function () {
        setStatus($(this).find("#ai-polish-status"), "AI Polish is not initialized (missing AJAX config).", true);
      });
    }
  }

  $(function () {
    if (window.aiPolish && aiPolish.isSettings) initSettings();
    // Metabox behavior is handled inline in PHP (admin/metabox.php) to avoid conflicts
    // with Classic Editor / Gutenberg and other admin JS.
  });
})(window.jQuery);
