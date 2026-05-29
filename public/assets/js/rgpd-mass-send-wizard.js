(function () {
    function syncCardSelection(checkbox, cardSelector) {
        var card = checkbox.closest(cardSelector);
        if (card) {
            card.classList.toggle('is-selected', checkbox.checked);
        }
    }

    /* —— Paso 1: plantillas —— */
    var step1Form = document.getElementById('rgpdMassSendStep1');
    if (step1Form) {
        var tplCountEl = document.getElementById('rgpdWizardTemplateCount');
        var tplSubmit = document.getElementById('rgpdWizardStep1Submit');

        function updateTemplateCount() {
            var n = step1Form.querySelectorAll('.rgpd-wizard-template-cb:checked').length;
            if (tplCountEl) {
                tplCountEl.textContent = n + (n === 1 ? ' plantilla seleccionada' : ' plantillas seleccionadas');
            }
            if (tplSubmit) {
                tplSubmit.disabled = n < 1;
            }
        }

        step1Form.querySelectorAll('.rgpd-wizard-template-cb').forEach(function (cb) {
            syncCardSelection(cb, '.rgpd-wizard-template-card');
            cb.addEventListener('change', function () {
                syncCardSelection(cb, '.rgpd-wizard-template-card');
                updateTemplateCount();
            });
        });

        updateTemplateCount();
    }

    /* —— Paso 2: comunidades (multi) —— */
    var communityPicker = document.getElementById('rgpdWizardCommunityPicker');
    if (communityPicker) {
        var communityCountEl = document.getElementById('rgpdWizardCommunityCount');
        var btnSelectAllCommunities = document.getElementById('rgpdWizardSelectAllCommunities');
        var btnClearAllCommunities = document.getElementById('rgpdWizardClearAllCommunities');
        var reloadTimer = null;

        function updateCommunityCountLabel() {
            if (!communityCountEl) {
                return;
            }
            var n = communityPicker.querySelectorAll('.rgpd-wizard-community-cb:checked').length;
            communityCountEl.textContent = n + ' comunidad' + (n === 1 ? '' : 'es') + ' seleccionada' + (n === 1 ? '' : 's');
        }

        function selectedCommunityIds() {
            return Array.prototype.slice.call(
                communityPicker.querySelectorAll('.rgpd-wizard-community-cb:checked')
            ).map(function (cb) {
                return cb.value;
            });
        }

        function reloadWithCommunities() {
            var ids = selectedCommunityIds();
            var url = new URL(window.location.href);
            url.searchParams.set('step', '2');
            url.searchParams.delete('community_id');
            url.searchParams.delete('community_ids[]');
            ids.forEach(function (id) {
                url.searchParams.append('community_ids[]', id);
            });
            window.location.href = url.toString();
        }

        communityPicker.querySelectorAll('.rgpd-wizard-community-cb').forEach(function (cb) {
            syncCardSelection(cb, '.rgpd-wizard-community-card');
            cb.addEventListener('change', function () {
                syncCardSelection(cb, '.rgpd-wizard-community-card');
                updateCommunityCountLabel();
                clearTimeout(reloadTimer);
                reloadTimer = setTimeout(reloadWithCommunities, 150);
            });
        });

        if (btnSelectAllCommunities) {
            btnSelectAllCommunities.addEventListener('click', function () {
                communityPicker.querySelectorAll('.rgpd-wizard-community-cb').forEach(function (cb) {
                    cb.checked = true;
                    syncCardSelection(cb, '.rgpd-wizard-community-card');
                });
                updateCommunityCountLabel();
                reloadWithCommunities();
            });
        }

        if (btnClearAllCommunities) {
            btnClearAllCommunities.addEventListener('click', function () {
                communityPicker.querySelectorAll('.rgpd-wizard-community-cb').forEach(function (cb) {
                    cb.checked = false;
                    syncCardSelection(cb, '.rgpd-wizard-community-card');
                });
                updateCommunityCountLabel();
                reloadWithCommunities();
            });
        }
    }

    /* —— Paso 2: vecinos —— */
    var step2Form = document.getElementById('rgpdMassSendStep2');
    if (!step2Form) {
        return;
    }

    var activeFilter = 'selectable';
    var filterRole = document.getElementById('rgpdWizardFilterRole');
    var searchInput = document.getElementById('rgpdWizardSearch');
    var countEl = document.getElementById('rgpdWizardSelectedCount');
    var btnSelectVisible = document.getElementById('rgpdWizardSelectVisible');
    var btnClear = document.getElementById('rgpdWizardClearSelection');
    var chips = step2Form.querySelectorAll('.rgpd-wizard-chip');
    var communityBlocks = step2Form.querySelectorAll('.rgpd-wizard-community-block');

    function allResidentCols() {
        return step2Form.querySelectorAll('.rgpd-wizard-resident-col');
    }

    function getSelectableCheckboxesInContainer(container) {
        var checkboxes = [];
        container.querySelectorAll('.rgpd-wizard-resident-col').forEach(function (col) {
            if (col.dataset.selectable !== '1') {
                return;
            }
            var cb = col.querySelector('.rgpd-wizard-resident-cb');
            if (cb) {
                checkboxes.push(cb);
            }
        });
        return checkboxes;
    }

    function allSelectableCheckedInContainer(container) {
        var checkboxes = getSelectableCheckboxesInContainer(container);
        if (checkboxes.length === 0) {
            return false;
        }
        return checkboxes.every(function (cb) {
            return cb.checked;
        });
    }

    function setSelectableInContainer(container, checked) {
        getSelectableCheckboxesInContainer(container).forEach(function (cb) {
            cb.checked = checked;
            syncCardSelection(cb, '.rgpd-wizard-resident-card');
        });
    }

    function updateCommunityToggleLabel(btn, block) {
        var label = btn.querySelector('.rgpd-wizard-toggle-all-label');
        if (!label) {
            return;
        }
        label.textContent = allSelectableCheckedInContainer(block)
            ? 'Deseleccionar todos'
            : 'Seleccionar todos';
    }

    function updateAllCommunityToggleLabels() {
        step2Form.querySelectorAll('.rgpd-wizard-toggle-all-community').forEach(function (btn) {
            var communityId = btn.dataset.communityId;
            var block = step2Form.querySelector('.rgpd-wizard-community-block[data-community-id="' + communityId + '"]');
            if (block) {
                updateCommunityToggleLabel(btn, block);
            }
        });
    }

    function updateCounts() {
        var total = step2Form.querySelectorAll('.rgpd-wizard-resident-cb:checked').length;
        if (countEl) {
            countEl.textContent = total + (total === 1 ? ' vecino seleccionado' : ' vecinos seleccionados');
        }

        communityBlocks.forEach(function (block) {
            var communityId = block.dataset.communityId;
            var countNode = block.querySelector('[data-community-count="' + communityId + '"]');
            if (!countNode) {
                return;
            }
            var n = block.querySelectorAll('.rgpd-wizard-resident-cb:checked').length;
            countNode.textContent = n + ' seleccionado' + (n === 1 ? '' : 's');
        });

        updateAllCommunityToggleLabels();
    }

    function matchesFilter(col) {
        var pending = parseInt(col.dataset.pending || '0', 10);
        var unsent = parseInt(col.dataset.unsent || '0', 10);
        var selectable = col.dataset.selectable === '1';

        if (activeFilter === 'selectable') {
            return selectable;
        }
        if (activeFilter === 'pending') {
            return pending > 0;
        }
        if (activeFilter === 'unsent') {
            return unsent > 0;
        }
        return true;
    }

    function applyFilters() {
        var role = filterRole ? filterRole.value : 'all';
        var q = searchInput ? searchInput.value.trim().toLowerCase() : '';

        allResidentCols().forEach(function (col) {
            var ok = matchesFilter(col);
            if (role === 'presidents' && col.dataset.president !== '1') {
                ok = false;
            }
            if (role === 'residents' && col.dataset.president === '1') {
                ok = false;
            }
            if (q && !(col.dataset.name || '').includes(q)) {
                ok = false;
            }
            col.style.display = ok ? '' : 'none';
        });
    }

    chips.forEach(function (chip) {
        chip.addEventListener('click', function () {
            chips.forEach(function (c) {
                c.classList.remove('is-active');
            });
            chip.classList.add('is-active');
            activeFilter = chip.dataset.filter || 'all';
            applyFilters();
        });
    });

    step2Form.querySelectorAll('.rgpd-wizard-resident-cb').forEach(function (cb) {
        syncCardSelection(cb, '.rgpd-wizard-resident-card');
        cb.addEventListener('change', function () {
            syncCardSelection(cb, '.rgpd-wizard-resident-card');
            updateCounts();
        });
    });

    step2Form.querySelectorAll('.rgpd-wizard-toggle-all-community').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var communityId = btn.dataset.communityId;
            var block = step2Form.querySelector('.rgpd-wizard-community-block[data-community-id="' + communityId + '"]');
            if (!block) {
                return;
            }

            var shouldSelectAll = !allSelectableCheckedInContainer(block);
            setSelectableInContainer(block, shouldSelectAll);
            updateCommunityToggleLabel(btn, block);
            updateCounts();
        });
    });

    if (filterRole) {
        filterRole.addEventListener('change', applyFilters);
    }
    if (searchInput) {
        searchInput.addEventListener('input', applyFilters);
    }

    if (btnSelectVisible) {
        btnSelectVisible.addEventListener('click', function () {
            allResidentCols().forEach(function (col) {
                if (col.style.display === 'none') {
                    return;
                }
                if (col.dataset.selectable !== '1') {
                    return;
                }
                var cb = col.querySelector('.rgpd-wizard-resident-cb');
                if (cb) {
                    cb.checked = true;
                    syncCardSelection(cb, '.rgpd-wizard-resident-card');
                }
            });
            updateCounts();
        });
    }

    if (btnClear) {
        btnClear.addEventListener('click', function () {
            step2Form.querySelectorAll('.rgpd-wizard-resident-cb').forEach(function (cb) {
                cb.checked = false;
                syncCardSelection(cb, '.rgpd-wizard-resident-card');
            });
            updateCounts();
        });
    }

    applyFilters();
    updateCounts();
})();