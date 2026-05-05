(function () {
  // Sidebar móvil tipo app (toggle)
  const shell = document.querySelector(".app-shell");
  const sidebar = document.querySelector(".sidebar");
  const mobileToggle = document.querySelector("[data-sidebar-mobile-toggle]");

  if (shell && sidebar && mobileToggle) {
    const setMobileState = (open) => {
      shell.classList.toggle("sidebar-mobile-open", open);
      document.body.classList.toggle("sidebar-mobile-open", open);
      mobileToggle.setAttribute("aria-expanded", open ? "true" : "false");
      mobileToggle.innerHTML = open ? '<i class="bi bi-x-lg"></i>' : '<i class="bi bi-list"></i>';
    };

    mobileToggle.addEventListener("click", () => {
      const open = shell.classList.contains("sidebar-mobile-open");
      setMobileState(!open);
    });

    // Cerrar al pulsar fuera del sidebar
    document.addEventListener("click", (event) => {
      if (!window.matchMedia("(max-width: 992px)").matches) return;
      if (!shell.classList.contains("sidebar-mobile-open")) return;
      const insideSidebar = sidebar.contains(event.target);
      const isToggle = mobileToggle.contains(event.target);
      if (!insideSidebar && !isToggle) {
        setMobileState(false);
      }
    });

    // Cerrar menú al navegar desde sidebar en móvil
    sidebar.querySelectorAll("a").forEach((a) => {
      a.addEventListener("click", () => {
        if (window.matchMedia("(max-width: 992px)").matches) {
          setMobileState(false);
        }
      });
    });

    // Cerrar al pasar a desktop
    window.addEventListener("resize", () => {
      if (!window.matchMedia("(max-width: 992px)").matches) {
        setMobileState(false);
      }
    });

    // Cerrar con tecla Escape
    document.addEventListener("keydown", (event) => {
      if (event.key !== "Escape") return;
      if (!shell.classList.contains("sidebar-mobile-open")) return;
      setMobileState(false);
    });
  }

  // Flash toasts autocierre
  document.querySelectorAll(".toast").forEach((toastEl) => {
    if (window.bootstrap && window.bootstrap.Toast) {
      const toast = window.bootstrap.Toast.getOrCreateInstance(toastEl, {
        autohide: true,
        delay: 4500
      });
      toast.show();
    }
  });

  // Confirm modal reutilizable para formularios/actions con data-confirm
  const ensureConfirmModal = () => {
    let modalEl = document.getElementById("appConfirmModal");
    if (modalEl) return modalEl;

    const wrapper = document.createElement("div");
    wrapper.innerHTML = `
      <div class="modal fade" id="appConfirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Confirmar acción</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
              <p class="mb-0" id="appConfirmModalText"></p>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
              <button type="button" class="btn btn-danger" id="appConfirmModalAccept">Confirmar</button>
            </div>
          </div>
        </div>
      </div>
    `;
    document.body.appendChild(wrapper.firstElementChild);
    return document.getElementById("appConfirmModal");
  };

  let pendingSubmitForm = null;
  document.addEventListener("submit", (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (form.dataset.confirmed === "1") {
      delete form.dataset.confirmed;
      return;
    }
    const message = form.getAttribute("data-confirm");
    if (!message) return;

    event.preventDefault();
    const modalEl = ensureConfirmModal();
    const textEl = modalEl.querySelector("#appConfirmModalText");
    const acceptBtn = modalEl.querySelector("#appConfirmModalAccept");
    const modal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
    textEl.textContent = message;
    pendingSubmitForm = form;
    modal.show();

    const onAccept = () => {
      if (pendingSubmitForm) {
        pendingSubmitForm.dataset.confirmed = "1";
        pendingSubmitForm.requestSubmit();
        pendingSubmitForm = null;
      }
      acceptBtn.removeEventListener("click", onAccept);
    };
    acceptBtn.addEventListener("click", onAccept);
  });

  const chartEl = document.querySelector("#cae-status-chart");
  if (chartEl && typeof ApexCharts !== "undefined") {
    let series = [0, 0, 0, 0];
    let labels = ["Aprobado", "En revisión", "Pendiente", "Rechazado"];

    try {
      series = JSON.parse(chartEl.dataset.series || "[0,0,0,0]");
      labels = JSON.parse(chartEl.dataset.labels || '["Aprobado","En revisión","Pendiente","Rechazado"]');
    } catch (e) {
      // keep defaults
    }

    const options = {
      chart: { type: "donut", height: 320 },
      series,
      labels,
      colors: ["#10b981", "#38bdf8", "#f59e0b", "#ef4444"],
      legend: { position: "bottom" },
      dataLabels: { enabled: true },
      noData: { text: "Sin datos" }
    };

    const chart = new ApexCharts(chartEl, options);
    chart.render();
  }

  // Activa pestaña bootstrap según hash (#c-tech, #pane-docs, etc.)
  const hash = window.location.hash;
  if (hash) {
    const trigger = document.querySelector('[data-bs-target="' + hash + '"]');
    if (trigger && window.bootstrap && window.bootstrap.Tab) {
      window.bootstrap.Tab.getOrCreateInstance(trigger).show();
    }
  }

  // Mantiene hash actualizado al cambiar pestañas
  document.querySelectorAll('[data-bs-toggle="tab"]').forEach((tabEl) => {
    tabEl.addEventListener('shown.bs.tab', (event) => {
      const target = event.target.getAttribute('data-bs-target');
      if (target) history.replaceState(null, '', target);
    });
  });

  // Preview de archivos en modal (PDF/imagen/otros)
  const previewButtons = document.querySelectorAll("[data-file-preview]");
  if (previewButtons.length) {
    let modalEl = document.getElementById("filePreviewModal");
    if (!modalEl) {
      const wrapper = document.createElement("div");
      wrapper.innerHTML = `
        <div class="modal fade" id="filePreviewModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Vista previa de archivo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
              </div>
              <div class="modal-body p-0">
                <div id="filePreviewBody" class="file-preview-body"></div>
              </div>
            </div>
          </div>
        </div>
      `;
      document.body.appendChild(wrapper.firstElementChild);
      modalEl = document.getElementById("filePreviewModal");
    }

    const modalBody = document.getElementById("filePreviewBody");
    const modalTitle = modalEl.querySelector(".modal-title");
    const previewModal = window.bootstrap.Modal.getOrCreateInstance(modalEl);

    const renderPreview = (url, name) => {
      const safeUrl = String(url || "");
      const safeName = String(name || "Archivo");
      const lower = safeUrl.toLowerCase();

      modalTitle.textContent = safeName;
      modalBody.innerHTML = "";

      const isPdf = lower.endsWith(".pdf");
      const isImage = [".jpg", ".jpeg", ".png", ".gif", ".webp", ".bmp", ".svg"].some((ext) => lower.endsWith(ext));

      if (isPdf) {
        modalBody.innerHTML = `<iframe src="${safeUrl}" class="file-preview-iframe" title="Vista previa PDF"></iframe>`;
        return;
      }

      if (isImage) {
        modalBody.innerHTML = `<div class="file-preview-image-wrap"><img src="${safeUrl}" alt="${safeName}" class="file-preview-image"></div>`;
        return;
      }

      modalBody.innerHTML = `
        <div class="file-preview-fallback">
          <p class="mb-3">No hay previsualizacion embebida para este tipo de archivo.</p>
          <a class="btn btn-outline-primary btn-sm" href="${safeUrl}" target="_blank" rel="noopener">Abrir en nueva pestaña</a>
        </div>
      `;
    };

    previewButtons.forEach((btn) => {
      btn.addEventListener("click", () => {
        const url = btn.getAttribute("data-file-preview-url") || "";
        const name = btn.getAttribute("data-file-preview-name") || "Archivo";
        if (!url) return;
        renderPreview(url, name);
        previewModal.show();
      });
    });

    modalEl.addEventListener("hidden.bs.modal", () => {
      modalBody.innerHTML = "";
    });
  }

  // Sidebar: grupo desplegable CAE + persistencia localStorage
  const sidebarToggles = document.querySelectorAll("[data-sidebar-toggle]");

    const setSidebarGroupState = (btn, target, open) => {
      target.classList.toggle("open", open);
      target.setAttribute("data-open", open ? "1" : "0");
      btn.setAttribute("aria-expanded", open ? "true" : "false");

      // max-height dinámico para que siempre cierre/abra bien
      if (open) {
        target.style.maxHeight = target.scrollHeight + "px";
      } else {
        target.style.maxHeight = "0px";
      }
    };

    sidebarToggles.forEach((btn) => {
      const targetId = btn.getAttribute("data-sidebar-toggle");
      const target = document.getElementById(targetId);
      if (!target) return;

      const storageKey = "sidebar." + targetId + ".open";
      const saved = window.localStorage.getItem(storageKey);
      const defaultOpen = target.getAttribute("data-open") === "1";
      const shouldOpen = saved === null ? defaultOpen : saved === "1";

      setSidebarGroupState(btn, target, shouldOpen);

      btn.addEventListener("click", () => {
        const isOpen = target.classList.contains("open");
        const next = !isOpen;
        setSidebarGroupState(btn, target, next);
        window.localStorage.setItem(storageKey, next ? "1" : "0");
      });

      // En móvil: al navegar desde subitem, colapsa grupo para UX tipo app
      target.querySelectorAll(".app-sub-link").forEach((subLink) => {
        subLink.addEventListener("click", () => {
          if (window.matchMedia("(max-width: 992px)").matches) {
            setSidebarGroupState(btn, target, false);
            window.localStorage.setItem(storageKey, "0");
          }
        });
      });

      // Recalcula altura al redimensionar si está abierto
      window.addEventListener("resize", () => {
        if (target.classList.contains("open")) {
          target.style.maxHeight = target.scrollHeight + "px";
        }
      });
  });
})();