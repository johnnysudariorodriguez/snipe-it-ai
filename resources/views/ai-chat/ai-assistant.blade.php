@extends('layouts.default')

@section('content')
    @can('admin')
        <section class="content-header">
            <h1>
                AI Assistant — Knowledge Base
                <small>Manage documents used by the AI knowledge base</small>
            </h1>
        </section>

        <section class="content">
            <div class="row">
                <div class="col-md-4">
                    <div class="box box-primary">
                        <div class="box-header with-border">
                            <h3 class="box-title">Upload Document</h3>
                        </div>

                        <div class="box-body">

                            <!-- DROP AREA (custom, no Dropzone dependency) -->
                            <div id="kb-top-alert" style="display:none;margin-bottom:10px"></div>

                            <div id="kb-drop-area" class="kb-dropzone" role="button" tabindex="0">
                                <input id="kb-file-input" type="file" accept=".pdf,.doc,.docx,.txt" multiple
                                    style="display:none" />
                                <div class="dz-inner">
                                    <div class="dz-icon">
                                        <i class="fa fa-cloud-upload"></i>
                                    </div>

                                    <div class="dz-title">
                                        Click to upload or drag and drop
                                    </div>

                                    <div class="dz-sub">
                                        PDF, DOC, DOCX or TXT (max 10MB)
                                    </div>
                                </div>
                            </div>
                            
                            <!-- STAGING -->
                            <div id="kb-staging-container" style="display:none;margin-top:12px">
                                <button id="kb-finalize-btn" class="btn btn-primary btn-sm">
                                    Finalize Uploads
                                </button>
                            </div>

                            <!-- Upload list (table) -->
                            <div style="margin-top:12px">
                                <table class="table table-condensed table-hover" id="kb-upload-table">
                                    <thead>
                                        <tr>
                                            <th>File Name</th>
                                            <th style="width:110px">Size</th>
                                            <th style="width:80px">Delete</th>
                                        </tr>
                                    </thead>
                                    <tbody id="kb-upload-tbody"></tbody>
                                </table>
                            </div>


                            {{-- <!-- OVERALL PROGRESS -->
                            <div id="kb-overall-wrap" style="display:none;margin-top:12px">
                                <div id="kb-overall-text" style="text-align:center;font-size:12px;margin-bottom:6px;"></div>
                                <div id="kb-overall-track">
                                    <div id="kb-overall-bar"></div>
                                </div>
                            </div> --}}

                        </div>
                    </div>


                </div>

                <style>
                    #myDropzone {
                        border: 2px dashed #cfd6e4;
                        border-radius: 10px;
                        background: #fafbfc;
                        padding: 30px;
                        min-height: 140px;
                        cursor: pointer;
                    }

                    .dz-message {
                        text-align: center;
                    }

                    .dz-icon {
                        font-size: 28px;
                        color: #6b63ff;
                        margin-bottom: 8px;
                    }

                    .dz-title {
                        font-weight: 600;
                        color: #222;
                    }

                    .dz-sub {
                        font-size: 12px;
                        color: #6c757d;
                    }

                    #kb-upload-list .dz-preview {
                        padding: 10px;
                        border: 1px solid #eee;
                        border-radius: 6px;
                        margin-top: 8px;
                        background: #fff;
                    }

                    #kb-overall-track {
                        height: 8px;
                        background: #e9ecef;
                        border-radius: 10px;
                        overflow: hidden;
                    }

                    #kb-overall-bar {
                        height: 100%;
                        width: 0%;
                        background: #28a745;
                        transition: width .2s ease;
                    }
                </style>

                <div class="col-md-8">
                    <div class="box">
                        <div class="box-header with-border">
                            <h3 class="box-title">Knowledge Base Documents</h3>
                            <div class="box-tools pull-right">
                                <button id="kb-refresh" class="btn btn-default btn-sm">Refresh</button>
                            </div>
                        </div>

                        <div class="box-body">
                            <div id="kb-empty-state" class="text-center" style="display:none;padding:40px">
                                <i class="fa fa-folder-open fa-4x" style="color:#d2d6de"></i>
                                <h4 style="margin-top:12px">No documents uploaded yet</h4>
                                <p class="text-muted">Upload documents to make them available to the AI Assistant.</p>
                                <button id="kb-empty-upload" class="btn btn-primary">Upload Document</button>
                            </div>

                            <div id="kb-table-wrap">
                                <table class="table table-striped" id="kb-files-table">
                                    <thead>
                                                <tr>
                                                    <th>Document Name</th>
                                                    <th>File Type</th>
                                                    <th>Upload Date</th>
                                                    <th>Uploaded By</th>
                                                    <th class="text-right">Delete</th>
                                                </tr>
                                    </thead>
                                    <tbody id="kb-files-tbody">
                                        <!-- rows rendered by JS -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Details modal -->
            <div class="modal fade" id="kb-details-modal" tabindex="-1" role="dialog">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                            <h4 class="modal-title" id="kb-modal-title">Document Details</h4>
                        </div>
                        <div class="modal-body" id="kb-modal-body">
                            <!-- details injected by JS -->
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <style>
            /* New Dropzone look to match provided image */
            #myDropzone {
                border: 2px dashed #d6e6f2;
                border-radius: 12px;
                background: #ffffff;
                padding: 28px;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            #myDropzone .dz-default.dz-message {
                width: 100%;
                text-align: center
            }

            #myDropzone .dz-inner {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 8px
            }

            #myDropzone .dz-icon {
                width: 48px;
                height: 48px;
                border-radius: 8px;
                display: flex;
                align-items: center;
                justify-content: center;
                background: transparent
            }

            #myDropzone .dz-icon .fa {
                color: #6b63ff;
                font-size: 24px
            }

            #myDropzone .dz-title {
                font-weight: 600;
                color: #222
            }

            #myDropzone .dz-sub {
                font-size: 12px;
                color: #6c757d
            }

            #myDropzone.dz-drag-hover {
                border-color: #6b63ff;
                background: #fbfbff
            }

            .kb-dropzone {
                border: 2px dashed #d2d6de;
                border-radius: 6px;
                padding: 34px;
                cursor: pointer;
                text-align: center;
            }

            .kb-dropzone.dragover {
                border-color: #3c8dbc;
                background: #f7fbfd
            }

            .kb-dropzone .lead {
                font-weight: 600
            }

            /* Larger progress bar look */
            .kb-progress {
                height: 12px;
                background: #f1f1f1;
                border-radius: 6px;
                overflow: hidden
            }

            .kb-progress>.bar {
                height: 12px;
                background: #1e90ff;
                width: 0
            }

            /* Staging list */
            #kb-staging-list>div {
                border-top: 1px solid #eee;
                padding: 8px 0
            }

            #kb-staging-list .btn-danger {
                margin-left: 8px
            }

            /* Overall progress */
            #kb-overall-wrap {
                margin-top: 12px
            }

            /* Overall progress (styled to match screenshot) */
            #kb-overall-track {
                height: 10px;
                background: #eef3f6;
                border-radius: 8px;
                overflow: hidden;
                position: relative;
            }

            #kb-overall-bar {
                height: 100%;
                width: 0;
                background: linear-gradient(90deg, #28a745, #1aa34a);
                border-radius: 8px;
                transition: width .3s ease;
            }

            #kb-overall-text {
                font-size: 12px;
                color: #333;
                margin-bottom: 6px;
            }

            /* Top alert area */
            #kb-top-alert .alert {
                margin: 0;
            }

            .kb-file-icon {
                display: inline-block;
                width: 28px;
                height: 28px;
                border-radius: 6px;
                background: #f0f7ff;
                color: #1e6fbf;
                text-align: center;
                line-height: 28px;
                margin-right: 8px
            }

            .kb-upload-item {
                margin-bottom: 8px;
            }

            .kb-skeleton td {
                background: linear-gradient(90deg, #f6f7f8 25%, #ededed 37%, #f6f7f8 63%);
                background-size: 400% 100%;
                animation: kb-loading 1.4s linear infinite
            }

            @keyframes kb-loading {
                0% {
                    background-position: 100% 50%
                }

                100% {
                    background-position: 0 50%
                }
            }
        </style>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const ALLOWED_MIMES = [
                    'application/pdf',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'text/plain'
                ];
                const ALLOWED_EXT = ['pdf', 'docx', 'doc', 'txt'];
                const LS_KEY = 'kb_files_v1';

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
                    const files = loadState();
                    s.forEach(it => files.unshift(it));
                    saveState(files);
                    sessionStorage.removeItem('kb_staging_v1');
                    if (uploadTbody) uploadTbody.innerHTML = '';
                    renderStaging();
                    renderFileList();
                    updateOverallProgress();
                    showTopAlert('Finalized ' + s.length + ' file(s)', 'success');
                }

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
    @endcan
@endsection
