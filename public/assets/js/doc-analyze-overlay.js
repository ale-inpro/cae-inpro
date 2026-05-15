(function () {
  "use strict";

  const OVERLAY_ID = "doc-analyze-overlay";

  function getOverlay() {
    const overlay = document.getElementById(OVERLAY_ID);
    if (!overlay) {
      return null;
    }
    if (overlay.parentElement && overlay.parentElement !== document.body) {
      document.body.appendChild(overlay);
    }
    return overlay;
  }

  function getSteps() {
    return Array.from(document.querySelectorAll("#" + OVERLAY_ID + " .doc-analyze-step"));
  }

  let stepInterval = null;

  function startStepAnimation() {
    const steps = getSteps();
    let i = 0;
    steps.forEach((s) => s.classList.remove("active", "done"));
    clearInterval(stepInterval);
    stepInterval = setInterval(() => {
      if (i > 0) {
        steps[i - 1]?.classList.replace("active", "done");
      }
      if (i < steps.length) {
        steps[i].classList.add("active");
        i += 1;
      } else {
        clearInterval(stepInterval);
      }
    }, 1100);
  }

  function show(options) {
    const overlay = getOverlay();
    if (!overlay) {
      return;
    }

    const title = options?.title || "Analizando documento";
    const titleEl = document.getElementById("doc-analyze-overlay-title");
    if (titleEl) {
      titleEl.textContent = title;
    }

    overlay.style.display = "flex";
    overlay.setAttribute("aria-hidden", "false");
    startStepAnimation();
  }

  /** Bloquea controles DESPUÉS de que el navegador inicie el POST (setTimeout 0). */
  function lockFormControls(form, options) {
    if (!form) {
      return;
    }
    const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
    const fileInputs = form.querySelectorAll('input[type="file"]');
    const selects = form.querySelectorAll("select");

    setTimeout(function () {
      if (submitBtn) {
        submitBtn.disabled = true;
        if (options?.submitButtonLoadingHtml) {
          submitBtn.innerHTML = options.submitButtonLoadingHtml;
        }
      }
      fileInputs.forEach(function (inp) {
        inp.disabled = true;
      });
      if (options?.lockSelects !== false) {
        selects.forEach(function (sel) {
          sel.disabled = true;
        });
      }
    }, 0);
  }

  function formHasFiles(form) {
    return Array.from(form.querySelectorAll('input[type="file"]')).some(function (inp) {
      return inp.files && inp.files.length > 0;
    });
  }

  /**
   * Enlaza submit: overlay + anti-doble-clic.
   * options: { title, requireFiles, noFilesMessageId, submitButtonLoadingHtml, lockSelects }
   */
  function bindForm(form, options) {
    if (!form || form.dataset.docAnalyzeBound === "1") {
      return;
    }
    form.dataset.docAnalyzeBound = "1";
    options = options || {};

    const noFilesEl = options.noFilesMessageId
      ? document.getElementById(options.noFilesMessageId)
      : null;

    form.addEventListener("submit", function (e) {
      if (form.dataset.skipDocAnalyze === "1") {
        return;
      }

      if (form.dataset.submitting === "1") {
        e.preventDefault();
        return;
      }

      if (options.requireFiles && !formHasFiles(form)) {
        e.preventDefault();
        if (noFilesEl) {
          noFilesEl.classList.remove("d-none");
        }
        return;
      }

      if (noFilesEl) {
        noFilesEl.classList.add("d-none");
      }

      form.dataset.submitting = "1";

      requestAnimationFrame(function () {
        show({ title: options.title });
        lockFormControls(form, options);
      });
    });
  }

  window.AppDocAnalyzeOverlay = { show, bindForm, lockFormControls, formHasFiles };
})();