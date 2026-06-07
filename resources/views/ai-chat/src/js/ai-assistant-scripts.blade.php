<script>
    document.addEventListener('DOMContentLoaded', function() {
        const ALLOWED_MIMES = [
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain'
        ];
        const ALLOWED_EXT = ['pdf', 'docx', 'doc', 'txt'];
        const LS_KEY = 'kb_files_v1';
        const stagedFiles = {};

        const fileInput = document.getElementById('kb-file-input');
        const dropArea = document.getElementById('kb-drop-area');
        const uploadTbody = document.getElementById('kb-upload-tbody');
        const tbody = document.getElementById('kb-files-tbody');
        const emptyState = document.getElementById('kb-empty-state');
        const emptyUploadBtn = document.getElementById('kb-empty-upload');
        const topAlertWrap = document.getElementById('kb-top-alert');
        const overallText = document.getElementById('kb-overall-text');
        const overallBar = document.getElementById('kb-overall-bar');
        const overallWrap = document.getElementById('kb-overall-wrap');

        function showTopAlert(msg, type = 'success') {
            if (!topAlertWrap) return;
            topAlertWrap.innerHTML = `<div class="alert alert-${type}" role="alert">${escapeHtml(msg)}</div>`;
            topAlertWrap.style.display = 'block';
            setTimeout(() => {
                if (topAlertWrap) {
                    topAlertWrap.style.display = 'none';
                    topAlertWrap.innerHTML = '';
                }
            }, 3500);
        }

        function showMessage(msg, type = 'success') {
            const el = document.createElement('div');
            el.className = 'alert alert-' + (type === 'danger' ? 'danger' : (type === 'warning' ? 'warning' :
                'success'));
            el.textContent = msg;
            el.style.position = 'fixed';
            el.style.right = '20px';
            el.style.bottom = '20px';
            el.style.zIndex = 9999;
            document.body.appendChild(el);
            setTimeout(() => el.remove(), 3500);
        }

        function generateId() {
            return Date.now().toString(36) + Math.random().toString(36).slice(2, 6);
        }

        function escapeHtml(s) {
            if (!s) return '';
            return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        // persistence for finalized files
        function loadState() {
            try {
                return JSON.parse(localStorage.getItem(LS_KEY) || '[]');
            } catch (e) {
                return [];
            }
        }

        function saveState(s) {
            localStorage.setItem(LS_KEY, JSON.stringify(s));
        }

        function renderFileList() {
            const files = loadState();
            tbody.innerHTML = '';
            if (!files || files.length === 0) {
                document.getElementById('kb-empty-state').style.display = 'block';
                return;
            }
            document.getElementById('kb-empty-state').style.display = 'none';
            files.forEach(f => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${escapeHtml(f.name)}</td>
                    <td>${escapeHtml(f.type)}</td>
                    <td>${new Date(f.uploadedAt).toLocaleString()}</td>
                    <td>Admin</td>
                    <td class="text-right">
                        <img src="/img/chatbot/delete.png" alt="Delete" data-id="${f.id}" data-action="delete" style="width:16px;height:16px;cursor:pointer"/>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }

        function statusBadge(status) {
            const cls = status === 'Indexed' ? 'label-success' : status === 'Processing' ? 'label-warning' :
                status === 'Pending' ? 'label-default' : 'label-danger';
            return `<span class="label ${cls}">${escapeHtml(status)}</span>`;
        }

        function validateFile(file) {
            if (!file) return {
                valid: false,
                reason: 'No file'
            };
            if (file.size === 0) return {
                valid: false,
                reason: 'File is empty'
            };
            const ext = (file.name.split('.').pop() || '').toLowerCase();
            const mimeOk = ALLOWED_MIMES.includes(file.type);
            const extOk = ALLOWED_EXT.includes(ext);
            if (!mimeOk && !extOk) return {
                valid: false,
                reason: 'Invalid file type'
            };
            return {
                valid: true,
                ext
            };
        }

        // staging (session)
        function loadStaging() {
            try {
                return JSON.parse(sessionStorage.getItem('kb_staging_v1') || '[]');
            } catch (e) {
                return [];
            }
        }

        function saveStaging(s) {
            sessionStorage.setItem('kb_staging_v1', JSON.stringify(s));
        }

        function addToStaging(meta) {
            const s = loadStaging();
            s.unshift(meta);
            saveStaging(s);
            renderStaging();
        }

        function processFileList(fileList) {
            if (!fileList || fileList.length === 0) return;
            Array.from(fileList).forEach(file => {
                const check = validateFile(file);
                if (!check.valid) {
                    showMessage('Invalid file: ' + file.name + ' — ' + check.reason, 'danger');
                    return;
                }
                const meta = {
                    id: generateId(),
                    name: file.name,
                    type: check.ext || (file.type || '').split('/').pop(),
                    size: file.size,
                    uploadedAt: new Date().toISOString(),
                    status: 'Pending'
                };

                // keep actual File object in memory until finalized
                stagedFiles[meta.id] = file;

                // UI progress (table row)
                const barId = 'progress-' + generateId();
                const tr = document.createElement('tr');
                tr.dataset.id = meta.id;
                tr.innerHTML = `
                    <td>
                        <div style="display:flex;flex-direction:column">
                            <strong>${escapeHtml(file.name)}</strong>
                            <div class="kb-progress" style="margin-top:6px"><div class="bar" id="${barId}"></div></div>
                        </div>
                    </td>
                    <td>${(file.size/1024).toFixed(2)} KB</td>
                    <td class="text-center"><img src="/img/chatbot/delete.png" alt="Delete" data-id="${meta.id}" data-action="upload-delete" style="width:16px;height:16px;cursor:pointer"/></td>
                `;
                if (uploadTbody) uploadTbody.appendChild(tr);

                simulateProgress(barId, () => {
                    addToStaging(meta);
                    showTopAlert('Uploaded to staging: ' + file.name, 'success');
                });
            });
        }

        function simulateProgress(barId, cb) {
            let pct = 0;
            const el = () => document.getElementById(barId);
            const iv = setInterval(() => {
                pct = Math.min(100, pct + Math.floor(Math.random() * 25) + 5);
                const e = el();
                if (e) {
                    e.style.width = pct + '%';
                }
                if (pct >= 100) {
                    clearInterval(iv);
                    if (cb) cb();
                }
            }, 180);
        }

        function renderStaging() {
            const s = loadStaging();
            const container = document.getElementById('kb-staging-container');
            const list = document.getElementById('kb-staging-list');
            const tbodyStaging = document.getElementById('kb-staging-tbody');
            if (!s || s.length === 0) {
                if (container) container.style.display = 'none';
                if (overallWrap) overallWrap.style.display = 'none';
                if (list) list.innerHTML = '';
                if (tbodyStaging) tbodyStaging.innerHTML = '';
                const fb = document.getElementById('kb-finalize-btn');
                if (fb) fb.disabled = true;
                return;
            }
            if (container) container.style.display = 'block';
            if (overallWrap) overallWrap.style.display = 'block';
            if (list) list.innerHTML = '';
            if (tbodyStaging) tbodyStaging.innerHTML = '';
            s.forEach(item => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="text-center"><button class="btn btn-xs btn-default" data-id="${item.id}" data-action="view-staged"><i class="fa fa-eye"></i></button></td>
                    <td>${escapeHtml(item.name)}</td>
                    <td>${(item.size/1024).toFixed(2)} KB</td>
                    <td class="text-center"><img src="/img/chatbot/delete.png" alt="Remove" data-id="${item.id}" data-action="unstage" style="width:16px;height:16px;cursor:pointer"/></td>
                `;
                if (tbodyStaging) tbodyStaging.appendChild(tr);
            });
            const fb = document.getElementById('kb-finalize-btn');
            if (fb) fb.disabled = false;
            updateOverallProgress();
        }

        function updateOverallProgress() {
            const s = loadStaging();
            const total = s.reduce((acc, i) => acc + (i.size || 0), 0);
            const cap = 15 * 1024 * 1024;
            const pct = Math.min(100, Math.round((total / cap) * 100));
            if (overallBar) overallBar.style.width = pct + '%';
            if (overallText) overallText.textContent = `${(total/1024/1024).toFixed(2)} MB out of 15 MB`;
            if (overallWrap) overallWrap.style.display = (total > 0) ? 'block' : 'none';
        }

        function finalizeUploads() {
            const s = loadStaging();
            if (!s || s.length === 0) {
                showMessage('No staged files', 'warning');
                return;
            }
            // perform real uploads to backend Laravel -> Python service
            const filesState = loadState();
            const uploadUrl = '{{ route('api.ai.upload') }}';

            (async () => {
                // work on a copy of staging so we can modify it
                let staging = loadStaging();
                for (const meta of Array.from(staging)) {
                    const file = stagedFiles[meta.id];
                    if (!file) {
                        // no file blob available (shouldn't happen)
                        meta.status = 'Error';
                        // update staging item
                        const idx = staging.findIndex(x => x.id === meta.id);
                        if (idx !== -1) staging[idx] = meta;
                        saveStaging(staging);
                        continue;
                    }

                    const form = new FormData();
                    form.append('file', file, file.name);

                    try {
                        const resp = await fetch(uploadUrl, {
                            method: 'POST',
                            body: form,
                            credentials: 'same-origin',
                            headers: {
                                'Accept': 'application/json'
                            }
                        });

                        const ctype = resp.headers.get('content-type') || '';
                        let json = null;
                        let text = null;
                        if (ctype.indexOf('application/json') !== -1) {
                            json = await resp.json().catch(() => null);
                        } else {
                            text = await resp.text().catch(() => null);
                        }

                        if (resp.ok && json && json.status === 'ok') {
                            meta.status = 'Indexed';
                            meta.inserted = json.inserted || 0;
                            filesState.unshift(meta);
                            // remove from staging
                            staging = staging.filter(x => x.id !== meta.id);
                            delete stagedFiles[meta.id];
                            showTopAlert('Inserted ' + (json.inserted || 0) + ' chunks into knowledge base', 'success');
                        } else {
                            meta.status = 'Error';
                            let msg = 'Upload failed';
                            if (json && (json.detail || json.message)) msg = json.detail || json.message;
                            else if (text) msg = text.substring(0, 300);
                            // update staging with error status so user can retry
                            const idx = staging.findIndex(x => x.id === meta.id);
                            if (idx !== -1) staging[idx] = meta;
                            showTopAlert(msg, 'danger');
                        }

                    } catch (e) {
                        meta.status = 'Error';
                        const idx = staging.findIndex(x => x.id === meta.id);
                        if (idx !== -1) staging[idx] = meta;
                        showTopAlert('Upload failed: ' + e.message, 'danger');
                    }

                    saveState(filesState);
                    saveStaging(staging);
                }

                // clear upload table UI and re-render
                if (uploadTbody) uploadTbody.innerHTML = '';
                renderStaging();
                renderFileList();
                updateOverallProgress();
            })();
        }

        // Test Query button handler
        const testQueryBtn = document.getElementById('kb-test-query-btn');
        if (testQueryBtn) testQueryBtn.addEventListener('click', async function () {
            const q = prompt('Enter a test query to run against the knowledge base:');
            if (!q) return;
            const url = '{{ route('api.ai.chat') }}';
            try {
                const r = await fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ question: q })
                });
                const j = await r.json();
                const out = document.getElementById('kb-query-result');
                if (!out) return;
                if (!r.ok) {
                    out.innerHTML = '<div class="alert alert-danger">Query failed: ' + (j.message || r.statusText) + '</div>';
                    return;
                }

                // display brief preview
                const answer = j.answer || j.message || j.reply || '';
                const source = j.source || '';
                const steps = j.steps || [];
                out.innerHTML = `<div class="well"><strong>Answer (source: ${escapeHtml(source)})</strong><div style="margin-top:8px">${escapeHtml(answer)}</div><div style="margin-top:8px;color:#666">Steps: ${steps.length}</div></div>`;

            } catch (e) {
                const out = document.getElementById('kb-query-result');
                if (out) out.innerHTML = '<div class="alert alert-danger">Query error: ' + escapeHtml(e.message) + '</div>';
            }
        });

        // table actions (catch clicks on any element with data-action)
        tbody.addEventListener('click', function(e) {
            const el = e.target.closest('[data-action]');
            if (!el) return;
            const id = el.getAttribute('data-id');
            const action = el.getAttribute('data-action');
            if (action === 'delete') return handleDelete(id);
        });

        function handleDelete(id) {
            if (!confirm('Delete this document?')) return;
            const s = loadState();
            const idx = s.findIndex(x => x.id === id);
            if (idx === -1) {
                showMessage('Not found', 'danger');
                return;
            }
            s.splice(idx, 1);
            saveState(s);
            renderFileList();
            showMessage('Deleted', 'success');
        }

        function handleView(id) {
            const s = loadState();
            const item = s.find(x => x.id === id);
            if (!item) {
                showMessage('Not found', 'danger');
                return;
            }
            document.getElementById('kb-modal-title').textContent = item.name;
            document.getElementById('kb-modal-body').innerHTML =
                `<dl class="dl-horizontal"><dt>Name</dt><dd>${escapeHtml(item.name)}</dd><dt>Type</dt><dd>${escapeHtml(item.type)}</dd><dt>Size</dt><dd>${item.size} bytes</dd><dt>Uploaded</dt><dd>${new Date(item.uploadedAt).toLocaleString()}</dd><dt>Status</dt><dd>${escapeHtml(item.status)}</dd></dl>`;
            $('#kb-details-modal').modal('show');
        }

        function handleReindex(id, btn) {
            const s = loadState();
            const item = s.find(x => x.id === id);
            if (!item) {
                showMessage('Not found', 'danger');
                return;
            }
            item.status = 'Processing';
            saveState(s);
            renderFileList();
            const orig = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-pulse"></i> Processing';
            setTimeout(() => {
                item.status = 'Indexed';
                saveState(s);
                renderFileList();
                btn.disabled = false;
                btn.innerHTML = orig;
                showMessage('Re-index complete: ' + item.name, 'success');
            }, 2000 + Math.floor(Math.random() * 2000));
        }

        // staging actions
        const stagingTbody = document.getElementById('kb-staging-tbody');
        if (stagingTbody) stagingTbody.addEventListener('click', function(e) {
            const el = e.target.closest('[data-action]');
            if (!el) return;
            const id = el.getAttribute('data-id');
            const action = el.getAttribute('data-action');
            if (action === 'unstage') {
                const s = loadStaging();
                const idx = s.findIndex(x => x.id === id);
                if (idx !== -1) {
                    s.splice(idx, 1);
                    saveStaging(s);
                    renderStaging();
                    showMessage('Removed from staging', 'success');
                }
            }
            if (action === 'view-staged') {
                const s = loadStaging();
                const item = s.find(x => x.id === id);
                if (!item) return showMessage('Not found', 'danger');
                document.getElementById('kb-modal-title').textContent = item.name;
                document.getElementById('kb-modal-body').innerHTML =
                    `<dl class="dl-horizontal"><dt>Name</dt><dd>${escapeHtml(item.name)}</dd><dt>Type</dt><dd>${escapeHtml(item.type)}</dd><dt>Size</dt><dd>${item.size} bytes</dd><dt>Uploaded</dt><dd>${new Date(item.uploadedAt).toLocaleString()}</dd><dt>Status</dt><dd>${escapeHtml(item.status)}</dd></dl>`;
                $('#kb-details-modal').modal('show');
            }
        });

        // upload list actions (delete before/after staging)
        if (uploadTbody) uploadTbody.addEventListener('click', function(e) {
            const el = e.target.closest('[data-action]');
            if (!el) return;
            const id = el.getAttribute('data-id');
            const action = el.getAttribute('data-action');
            if (action === 'upload-delete') {
                // remove upload row
                const row = el.closest('tr');
                if (row) row.remove();
                // remove from staging if present
                const s = loadStaging();
                const idx = s.findIndex(x => x.id === id);
                if (idx !== -1) {
                    s.splice(idx, 1);
                    saveStaging(s);
                    renderStaging();
                    showMessage('Removed from staging', 'success');
                }
            }
        });

        // drag/drop handlers
        window.addEventListener('dragover', function(e) {
            e.preventDefault();
        });
        window.addEventListener('drop', function(e) {
            e.preventDefault();
        });
        if (dropArea) {
            dropArea.addEventListener('click', () => fileInput && fileInput.click());
            dropArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                dropArea.classList && dropArea.classList.add('dz-drag-hover');
            });
            dropArea.addEventListener('dragleave', (e) => {
                e.preventDefault();
                dropArea.classList && dropArea.classList.remove('dz-drag-hover');
            });
            dropArea.addEventListener('drop', (e) => {
                e.preventDefault();
                dropArea.classList && dropArea.classList.remove('dz-drag-hover');
                const files = e.dataTransfer ? e.dataTransfer.files : [];
                processFileList(files);
            });
        }

        if (fileInput) fileInput.addEventListener('change', function(e) {
            processFileList(e.target.files);
            fileInput.value = '';
        });
        document.getElementById('kb-refresh').addEventListener('click', renderFileList);
        if (emptyUploadBtn) emptyUploadBtn.addEventListener('click', () => fileInput && fileInput.click());
        const finalizeBtn = document.getElementById('kb-finalize-btn');
        if (finalizeBtn) finalizeBtn.addEventListener('click', finalizeUploads);

        // initial render
        renderFileList();
        renderStaging();
    });
</script>
