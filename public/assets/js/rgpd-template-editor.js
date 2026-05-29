(function () {
    const form = document.getElementById("rgpd-template-form");
    const editorHost = document.getElementById("tpl_body_editor");
    if (!form || !editorHost || typeof tinymce === "undefined") return;
  
    const hiddenInput = document.getElementById("body_html");
    const previewSamples = (() => {
      try {
        return JSON.parse(form.dataset.previewSamples || "{}");
      } catch (e) {
        return {};
      }
    })();
  
    const insertToken = (token) => {
      if (tinymce.activeEditor) {
        tinymce.activeEditor.insertContent(token);
        tinymce.activeEditor.focus();
      }
    };
  
    document.querySelectorAll("[data-insert-token]").forEach((btn) => {
      btn.addEventListener("click", () => insertToken(btn.getAttribute("data-insert-token") || ""));
    });
  
    tinymce.init({
      selector: "#tpl_body_editor",
      base_url: "https://cdn.jsdelivr.net/npm/tinymce@6.8.5",
      suffix: ".min",
      height: 420,
      menubar: false,
      statusbar: false,
      plugins: "lists link autoresize code",
      toolbar:
        "undo redo | blocks | bold italic underline | bullist numlist | alignleft aligncenter alignright | link | removeformat | code",
      block_formats: "Párrafo=p; Título principal=h2; Subtítulo=h3",
      content_style:
        "body{font-family:Inter,system-ui,sans-serif;font-size:15px;line-height:1.55;color:#0f172a;padding:12px;}",
      setup(editor) {
        editor.on("change keyup", () => {
          hiddenInput.value = editor.getContent();
        });
      },
    });
  
    form.addEventListener("submit", () => {
      if (tinymce.activeEditor) {
        hiddenInput.value = tinymce.activeEditor.getContent();
      }
    });
  
    const previewBtn = document.getElementById("rgpdTplPreviewBtn");
    const previewModalEl = document.getElementById("rgpdTplPreviewModal");
    const previewBody = document.getElementById("rgpdTplPreviewBody");
    if (previewBtn && previewModalEl && previewBody) {
      previewBtn.addEventListener("click", () => {
        let html = tinymce.activeEditor ? tinymce.activeEditor.getContent() : hiddenInput.value;
        Object.keys(previewSamples).forEach((token) => {
          html = html.split(token).join(previewSamples[token]);
        });
        previewBody.innerHTML = html;
        window.bootstrap.Modal.getOrCreateInstance(previewModalEl).show();
      });
    }
  })();