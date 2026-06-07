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

                <div class="col-md-8">
                    <div class="box">
                        <div class="box-header with-border">
                            <h3 class="box-title">Knowledge Base Documents</h3>
                            <div class="box-tools pull-right">
                                <button id="kb-refresh" class="btn btn-default btn-sm">Refresh</button>
                                <button id="kb-test-query-btn" class="btn btn-primary btn-sm" style="margin-left:6px">Test
                                    Query</button>
                            </div>
                        </div>

                        <div class="box-body">
                            <div id="kb-query-result" style="margin-top:12px"></div>
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

        @include('ai-chat.src.css.ai-assistant-styles')
        @include('ai-chat.src.js.ai-assistant-scripts')
    @endcan
@endsection
