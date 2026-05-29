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

    // Sidebar: grupos desplegables CAE/RGPD + persistencia localStorage
    const sidebarToggles = document.querySelectorAll("[data-sidebar-toggle]");

    const measureSidebarGroupHeight = (target) => {
      const hadOpen = target.classList.contains("open");
      if (!hadOpen) {
        target.classList.add("open");
      }
      const prevMax = target.style.maxHeight;
      target.style.maxHeight = "none";
      const height = target.scrollHeight;
      target.style.maxHeight = prevMax;
      if (!hadOpen) {
        target.classList.remove("open");
      }
      return height;
    };
  
    const setSidebarGroupState = (btn, target, open) => {
      target.classList.toggle("open", open);
      target.setAttribute("data-open", open ? "1" : "0");
      btn.setAttribute("aria-expanded", open ? "true" : "false");
  
      if (open) {
        target.style.maxHeight = measureSidebarGroupHeight(target) + "px";
      } else {
        target.style.maxHeight = "0px";
      }
    };
  
    const refreshOpenSidebarGroups = () => {
      document.querySelectorAll(".app-nav-group.open").forEach((group) => {
        group.style.maxHeight = measureSidebarGroupHeight(group) + "px";
      });
    };
  
    sidebarToggles.forEach((btn) => {
      const targetId = btn.getAttribute("data-sidebar-toggle");
      const target = document.getElementById(targetId);
      if (!target) return;
  
      const storageKey = "sidebar." + targetId + ".open";
      const pageDefaultOpen = target.getAttribute("data-open") === "1";
      const saved = window.localStorage.getItem(storageKey);
  
      // Sección activa: abierta al cargar. Otras: localStorage o cerrada por defecto.
      const shouldOpen = pageDefaultOpen
        ? true
        : saved === "1";
  
      setSidebarGroupState(btn, target, shouldOpen);
  
      btn.addEventListener("click", (event) => {
        event.preventDefault();
        event.stopPropagation();
  
        const isOpen = target.classList.contains("open");
        const next = !isOpen;
  
        setSidebarGroupState(btn, target, next);
        window.localStorage.setItem(storageKey, next ? "1" : "0");
  
        // Recalcular alturas de todos los grupos abiertos (evita bloqueo CAE -> RGPD)
        window.requestAnimationFrame(refreshOpenSidebarGroups);
      });
  
      target.querySelectorAll(".app-sub-link").forEach((subLink) => {
        subLink.addEventListener("click", () => {
          if (window.matchMedia("(max-width: 992px)").matches) {
            setSidebarGroupState(btn, target, false);
            window.localStorage.setItem(storageKey, "0");
          }
        });
      });
    });
  
    window.addEventListener("resize", () => {
      refreshOpenSidebarGroups();
    });
  
    window.requestAnimationFrame(refreshOpenSidebarGroups);
})();

// ── Polling de notificaciones + recarga contextual (comunidad / RL / técnicos) ─
(function () {
  const btn = document.querySelector('.app-notif-btn');
  if (!btn) return;

  const base = document.querySelector('meta[name="app-base-url"]')?.content ?? '';
  const area = window.location.pathname.includes('/admin/') ? 'admin' : 'gestor';
  const endpoint = base + '/' + area + '/notificaciones/poll';
  const POLL_MS = area === 'admin' ? 15_000 : 30_000;

  const ADMIN_COMMUNITY_OPEN_TYPES = [
    'rl_request_created',
    'rl_report_uploaded_by_gestor',
    'community_tech_not_preferred',
  ];
  const ADMIN_TECH_OPEN_TYPES = [
    'technician_association_requested',
    'technician_created_by_gestor',
  ];
  const GESTOR_OPEN_TYPES = [
    'technician_association_approved',
    'technician_association_rejected',
  ];

  function notificationOpenUrl(n) {
    const type = n.type ?? '';
    const id = n.id ?? 0;
    const payload = n.payload ?? {};
    if (id <= 0) return null;

    if (area === 'admin') {
      if (ADMIN_TECH_OPEN_TYPES.includes(type)) {
        return base + '/admin/notificaciones/' + id + '/open';
      }
      const cid = payload.community_id ?? 0;
      if (cid > 0 && ADMIN_COMMUNITY_OPEN_TYPES.includes(type)) {
        return base + '/admin/notificaciones/' + id + '/open';
      }
    }

    if (area === 'gestor' && GESTOR_OPEN_TYPES.includes(type)) {
      return base + '/gestor/notificaciones/' + id + '/open';
    }

    return null;
  }

  const badgeEl = () => document.querySelector('.app-notif-badge');
  let lastUnread = badgeEl() ? parseInt(badgeEl().textContent.trim(), 10) || 0 : 0;
  let lastReloadAt = 0;

  function currentCommunityIdFromPath() {
    const m = window.location.pathname.match(/\/comunidades\/(\d+)/);
    return m ? parseInt(m[1], 10) : 0;
  }

  function hasOpenModal() {
    return document.querySelector('.modal.show') !== null;
  }

  function schedulePageReload(reason) {
    const now = Date.now();
    if (now - lastReloadAt < 5000) return;
    lastReloadAt = now;

    const run = () => {
      if (hasOpenModal()) {
        window.setTimeout(run, 1500);
        return;
      }
      window.location.reload();
    };

    run();
  }

  function shouldReloadForNotification(n) {
    if (area !== 'admin' || !n) return false;

    const type = n.type ?? '';

    if (
      type === 'technician_association_requested' &&
      /\/admin\/tecnicos\/solicitudes/.test(window.location.pathname)
    ) {
      return true;
    }

    const payloadCid = n.payload?.community_id ?? 0;
    const pageCid = currentCommunityIdFromPath();
    if (payloadCid <= 0 || pageCid <= 0 || payloadCid !== pageCid) return false;

    const hash = window.location.hash;

    if (type === 'rl_request_created' || type === 'rl_report_uploaded_by_gestor') {
      return hash === '#c-rl';
    }

    if (type === 'community_tech_not_preferred') {
      return hash === '#c-tech';
    }

    return false;
  }

  function updateBadge(count) {
    let badge = badgeEl();
    if (count > 0) {
      if (!badge) {
        badge = document.createElement('span');
        badge.className =
          'position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger app-notif-badge';
        badge.style.fontSize = '0.6rem';
        btn.appendChild(badge);
      }
      badge.textContent = count > 99 ? '99+' : count;
      badge.style.display = '';
    } else if (badge) {
      badge.style.display = 'none';
    }
  }

  function updateDropdown(items) {
    const list = document.querySelector('.app-notif-list');
    if (!list) return;

    if (!items || items.length === 0) {
      list.innerHTML = '<p class="text-muted small mb-0 px-1">No tienes notificaciones.</p>';
      return;
    }

    list.innerHTML = items
      .map((n) => {
        const openUrl = notificationOpenUrl(n);
        const isClickable = openUrl !== null;

        const readClass = n.is_read ? 'text-muted' : 'fw-semibold';
        const inner = `
        <div class="${readClass}" style="${isClickable ? 'cursor:pointer;' : ''}">
          <div>${escapeHtml(n.title)}</div>
          <div class="fw-normal text-truncate">${escapeHtml(n.message)}</div>
          <div class="text-muted" style="font-size:0.7rem;">${n.created_fmt}</div>
        </div>`;

        return openUrl
          ? `<a href="${openUrl}" class="px-2 py-2 small border-bottom d-block text-decoration-none text-reset">${inner}</a>`
          : `<div class="px-2 py-2 small border-bottom">${inner}</div>`;
      })
      .join('');
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function showNotifToast(title, message, opts = {}) {
    if (!window.bootstrap) return;

    let stack = document.querySelector('.toast-stack');
    if (!stack) {
      stack = document.createElement('div');
      stack.className = 'toast-stack';
      document.body.appendChild(stack);
    }

    const communityId = opts.payload?.community_id ?? 0;
    const type = opts.type ?? '';
    const notifId = opts.id ?? 0;

    let actionUrl = base + '/' + area + '/notificaciones';
    let actionText = 'Ver todas las notificaciones →';

    const openUrl = notificationOpenUrl({ type, id: notifId, payload: opts.payload ?? {} });
    if (openUrl) {
      actionUrl = openUrl;
      if (type === 'technician_association_requested') {
        actionText = 'Ver solicitudes de asociación →';
      } else if (type === 'technician_created_by_gestor') {
        actionText = 'Ver ficha del técnico →';
      } else if (type === 'technician_association_approved') {
        actionText = 'Ver técnico en tu cartera →';
      } else if (type === 'technician_association_rejected') {
        actionText = 'Vincular otro técnico →';
      } else if (type === 'community_tech_not_preferred') {
        actionText = 'Ver técnicos de la comunidad →';
      } else if (type === 'rl_report_uploaded_by_gestor') {
        actionText = 'Ver informe RL en la comunidad →';
      } else {
        actionText = 'Ver solicitud en la comunidad →';
      }
    }

    const toast = document.createElement('div');
    toast.className = 'toast flash-toast flash-toast--info border-0 show';
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
      <div class="toast-header">
        <i class="bi bi-bell-fill text-warning me-2"></i>
        <strong class="me-auto">${escapeHtml(title)}</strong>
        <small>ahora</small>
        <button type="button" class="btn-close ms-2" data-bs-dismiss="toast"></button>
      </div>
      <div class="toast-body">
        ${escapeHtml(message)}
        <div class="mt-1">
          <a href="${actionUrl}" class="small text-decoration-none fw-semibold"
             onclick="event.preventDefault(); window.location.href='${actionUrl}'">
            ${actionText}
          </a>
        </div>
      </div>
    `;
    stack.appendChild(toast);

    bootstrap.Toast.getOrCreateInstance(toast, { delay: 6000 }).show();
    toast.addEventListener('hidden.bs.toast', () => toast.remove());
  }

  async function poll() {
    if (document.visibilityState === 'hidden') return;

    try {
      const res = await fetch(endpoint, { credentials: 'same-origin', cache: 'no-store' });
      if (!res.ok) return;
      const data = await res.json();

      const newUnread = data.unread ?? 0;

      if (newUnread > lastUnread) {
        btn.classList.add('app-notif-pulse');
        window.setTimeout(() => btn.classList.remove('app-notif-pulse'), 1000);

        const newest = data.items?.[0];

        if (shouldReloadForNotification(newest)) {
          schedulePageReload(newest.type);
          return;
        }

        if (area === 'admin' && newest?.type === 'technician_association_requested') {
          document.dispatchEvent(new CustomEvent('assoc-request-poll-now'));
        }

        if (newest && !newest.is_read) {
          showNotifToast(newest.title, newest.message, {
            id: newest.id ?? 0,
            type: newest.type ?? '',
            payload: newest.payload ?? null,
          });
        }
      }

      lastUnread = newUnread;
      updateBadge(newUnread);
      updateDropdown(data.items ?? []);
    } catch (_) {
      /* silencioso */
    }
  }

  poll();
  window.setInterval(poll, POLL_MS);

  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') poll();
  });
})();


// ── Sync pestaña Técnicos: recarga ante cualquier cambio de valoración/asignación ─
(function () {
  const techPane = document.getElementById('c-tech');
  if (!techPane) return;

  const area = window.location.pathname.includes('/admin/') ? 'admin' : 'gestor';
  if (area !== 'admin') return;

  const communityId = techPane.dataset.communityId || '';
  let lastVersion = techPane.dataset.techSyncVersion || '';
  if (!communityId || !lastVersion) return;

  const base = document.querySelector('meta[name="app-base-url"]')?.content ?? '';
  const syncUrl = base + '/admin/comunidades/' + communityId + '/sync';
  const POLL_MS = 15_000;
  let lastReloadAt = 0;

  function hasOpenModal() {
    return document.querySelector('.modal.show') !== null;
  }

  function schedulePageReload() {
    const now = Date.now();
    if (now - lastReloadAt < 5000) return;
    lastReloadAt = now;

    const run = () => {
      if (hasOpenModal()) {
        window.setTimeout(run, 1500);
        return;
      }
      window.location.reload();
    };

    run();
  }

  function isOnTechTab() {
    return window.location.hash === '#c-tech' || window.location.hash === '';
  }

  async function pollTechSync() {
    if (document.visibilityState === 'hidden') return;
    if (window.location.hash !== '#c-tech') return;

    try {
      const res = await fetch(syncUrl, { credentials: 'same-origin', cache: 'no-store' });
      if (!res.ok) return;

      const data = await res.json();
      const remoteVersion = String(data.tech_sync_version ?? '');

      if (remoteVersion && lastVersion && remoteVersion !== lastVersion) {
        schedulePageReload();
        return;
      }
    } catch (_) {
      /* silencioso */
    }
  }

  pollTechSync();
  window.setInterval(pollTechSync, POLL_MS);

  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') pollTechSync();
  });

  document.querySelectorAll('[data-bs-toggle="tab"]').forEach((tabEl) => {
    tabEl.addEventListener('shown.bs.tab', (event) => {
      const target = event.target.getAttribute('data-bs-target');
      if (target === '#c-tech') pollTechSync();
    });
  });
})();

// ── Sync solicitudes de asociación (admin: listado técnicos + pantalla solicitudes) ─
(function () {
  const base = document.querySelector('meta[name="app-base-url"]')?.content ?? '';
  if (!window.location.pathname.includes('/admin/tecnicos')) return;

  const syncUrl = base + '/admin/tecnicos/association-sync';
  const POLL_MS = 15_000;

  const solicitudesRoot = document.getElementById('admin-tecnicos-solicitudes');
  const listRoot = document.getElementById('admin-tecnicos-list');
  const isSolicitudesPage = /\/admin\/tecnicos\/solicitudes/.test(window.location.pathname);
  const isListPage = /\/admin\/tecnicos\/?$/.test(window.location.pathname);

  const initialPendingRaw = parseInt(
    solicitudesRoot?.dataset.assocPendingCount ??
      listRoot?.dataset.assocPendingCount ??
      '-1',
    10
  );
  let lastListVersion = solicitudesRoot?.dataset.assocListVersion ?? '';
  let lastPending = initialPendingRaw >= 0 ? initialPendingRaw : -1;
  let lastReloadAt = 0;

  function hasOpenModal() {
    return document.querySelector('.modal.show') !== null;
  }

  function updatePendingBadges(count) {
    document.querySelectorAll('[data-assoc-pending-badge]').forEach((el) => {
      if (count > 0) {
        el.textContent = count > 99 ? '99+' : String(count);
        el.classList.remove('is-hidden');
      } else {
        el.textContent = '';
        el.classList.add('is-hidden');
      }
    });

    const btn = document.getElementById('admin-assoc-requests-btn');
    if (btn) {
      btn.classList.toggle('has-pending', count > 0);
    }
  }

  function scheduleSolicitudesReload() {
    if (!isSolicitudesPage) return;
    const now = Date.now();
    if (now - lastReloadAt < 5000) return;
    lastReloadAt = now;

    const run = () => {
      if (hasOpenModal()) {
        window.setTimeout(run, 1500);
        return;
      }
      window.location.reload();
    };
    run();
  }

  async function pollAssocSync() {
    if (document.visibilityState === 'hidden') return;
    if (!isListPage && !isSolicitudesPage) return;

    try {
      const res = await fetch(syncUrl, { credentials: 'same-origin', cache: 'no-store' });
      if (!res.ok) return;
      const data = await res.json();

      const pending = parseInt(data.pending_count ?? 0, 10) || 0;
      const listVersion = String(data.list_version ?? '');

      if (pending !== lastPending) {
        if (lastPending >= 0 && pending > lastPending) {
          const assocBtn = document.getElementById('admin-assoc-requests-btn');
          assocBtn?.classList.add('app-notif-pulse');
          window.setTimeout(() => assocBtn?.classList.remove('app-notif-pulse'), 1200);
        }
        lastPending = pending;
        updatePendingBadges(pending);
      }

      if (isSolicitudesPage && listVersion && lastListVersion && listVersion !== lastListVersion) {
        scheduleSolicitudesReload();
        return;
      }
      if (isSolicitudesPage && listVersion) {
        lastListVersion = listVersion;
      }
    } catch (_) {
      /* silencioso */
    }
  }

  pollAssocSync();
  window.setInterval(pollAssocSync, POLL_MS);
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') pollAssocSync();
  });

  // Refresco inmediato al llegar notificación de nueva solicitud (sin esperar 15s)
  document.addEventListener('assoc-request-poll-now', () => pollAssocSync());
})();