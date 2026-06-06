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
