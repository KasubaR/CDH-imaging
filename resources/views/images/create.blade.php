@extends('layouts.app')

@section('title', 'Upload images — ' . $examination->patient->patient_name)
@section('topbar_desktop_title', 'Upload X-Ray')

@section('content')
<div class="canvas__inner upload-page">
    <div class="page-header">
        <p class="text-body-md page-header__lede">Add images to this examination.</p>
    </div>

    <div class="bento-card upload-card">
        <div class="card-header">
            <h3 class="text-headline-sm text-on-surface">Examination details</h3>
            <a href="{{ route('examinations.show', $examination) }}" class="btn btn--secondary">
                <span class="material-symbols-outlined">arrow_back</span>
                Back to examination
            </a>
        </div>
        <div class="card-body upload-context">
            <div class="upload-context__row">
                <span class="upload-context__label">Patient</span>
                <span class="upload-context__value">{{ $examination->patient->patient_name }}</span>
            </div>
            <div class="upload-context__row">
                <span class="upload-context__label">Examination</span>
                <span class="upload-context__value">{{ $examination->examinationType->name }} — {{ $examination->body_part }}</span>
            </div>
            <div class="upload-context__row">
                <span class="upload-context__label">Date</span>
                <span class="upload-context__value font-data-mono">{{ $examination->date_taken->format('Y-m-d') }}</span>
            </div>
            @if ($examination->description)
                <div class="upload-context__row">
                    <span class="upload-context__label">Description</span>
                    <span class="upload-context__value">{{ $examination->description }}</span>
                </div>
            @endif
        </div>

        <div class="card-body upload-form-body">
            @php
                $clientChunkBytes = min(4 * 1024 * 1024, (int) config('cdh.upload_chunk_max_bytes'));
                $imageMaxMb = max(1, (int) ceil($imageMaxBytes / (1024 * 1024)));
            @endphp
            <form
                method="post"
                action="{{ route('examinations.images.store', $examination) }}"
                enctype="multipart/form-data"
                data-upload-form
                data-examination-id="{{ $examination->id }}"
                data-chunk-upload-url="{{ route('examinations.images.chunks.store', $examination) }}"
                data-chunk-status-url-template="{{ route('examinations.images.chunks.status', ['examination' => $examination, 'uploadId' => '__UPLOAD_ID__']) }}"
                data-redirect-url="{{ route('examinations.show', $examination) }}"
                data-max-bytes="{{ $imageMaxBytes }}"
                data-chunk-bytes="{{ $clientChunkBytes }}"
            >
                @csrf

                <div class="upload-dropzone" data-dropzone tabindex="0" role="button" aria-label="Add X-ray images">
                    <span class="material-symbols-outlined upload-dropzone__icon">cloud_upload</span>
                    <p class="upload-dropzone__title">Drag &amp; drop X-ray files</p>
                    <p class="upload-dropzone__hint">JPG, JPEG or PNG — up to {{ $imageMaxMb }} MB per file</p>
                    <div class="upload-dropzone__actions">
                        <button type="button" class="btn btn--primary" data-browse-files>Browse Files</button>
                        <button type="button" class="btn btn--secondary" data-browse-folder hidden>Browse Folder</button>
                    </div>
                    <input type="file" data-file-input accept="image/jpeg,image/png,.jpg,.jpeg,.png" multiple hidden>
                    <input type="file" data-folder-input accept="image/jpeg,image/png,.jpg,.jpeg,.png" multiple webkitdirectory hidden>
                </div>

                <div class="upload-errors" data-upload-errors hidden></div>

                <div class="upload-selected" data-upload-selected hidden>
                    <div class="upload-selected__header">
                        <span>Selected</span>
                        <span data-selected-count>0 files</span>
                    </div>
                    <ul class="upload-file-list" data-file-list></ul>
                </div>

                <div class="upload-progress" data-upload-progress hidden>
                    <div class="upload-progress__track">
                        <div class="upload-progress__bar" data-upload-progress-bar></div>
                    </div>
                    <span class="upload-progress__label" data-upload-progress-label>0%</span>
                </div>

                <div class="form-stack__submit">
                    <button type="submit" class="btn btn--primary" data-upload-submit disabled>Upload</button>
                    <a href="{{ route('examinations.show', $examination) }}" class="btn btn--ghost">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
