@extends('layouts.app')

@section('title', $examination->patient->patient_name . ' — Examination')
@section('topbar_desktop_title', 'Examination viewer')

@section('content')
@php
    $patient = $examination->patient;
    $fallbackThumb = 'https://lh3.googleusercontent.com/aida-public/AB6AXuD7p7TDySDtsK1RUAl-fRoPBEBVIOaH0xQJtRlG_DYJ25vahuJhRYwMoJM1IeT2YqUXmoFqSeeriPVhC9fzjMYBbJA3T4iFQMlrYyHR6pv9jD_EEKCyCzD2iUxlMV2Lro2aMrCHLBE7XS2Yj4g_-UCd4K8C3tEGzCovMR80-y2z2fdSuSduvv_wzMsIODAQrFUEa6grT9K5gA6fF7e4_0J_4W7TtS3eotv4XaFWIckc8378YwbxXMRG';
    $images = $examination->images;
    $activeImage = $images->first();
    $mainImageUrl = $activeImage ? route('images.show', $activeImage) : $fallbackThumb;
    $viewLabel = $activeImage?->original_filename ?? strtoupper($examination->examinationType->code.' '.$examination->body_part);
    $canDownloadActive = $activeImage && auth()->user()->can('download', $activeImage);
@endphp

<div class="canvas__inner viewer-page">
    <div class="patient-header">
        <div class="patient-header__inner">
            <div class="patient-header__profile">
                <div class="patient-header__avatar">
                    <span class="material-symbols-outlined">patient_list</span>
                </div>
                <div>
                    <div class="patient-header__name-row">
                        <h2 class="text-headline-sm text-on-surface">{{ $patient->patient_name }}</h2>
                        @if ($patient->nrc)
                            <span class="patient-id-badge font-data-mono">NRC: {{ $patient->nrc }}</span>
                        @endif
                    </div>
                    <div class="patient-header__meta text-body-md">
                        <span class="patient-header__meta-item">
                            <span class="material-symbols-outlined">radiology</span>
                            {{ $examination->examinationType->name }} — {{ $examination->body_part }}
                        </span>
                        <span class="patient-header__meta-item">
                            <span class="material-symbols-outlined">calendar_today</span>
                            {{ $examination->date_taken->format('Y-m-d') }} {{ $examination->time_taken }}
                        </span>
                    </div>
                </div>
            </div>
            <div class="patient-header__actions">
                <a href="{{ route('patients.show', $patient) }}" class="btn btn--secondary">
                    <span class="material-symbols-outlined">arrow_back</span>
                    Patient record
                </a>
                @can('upload', [App\Models\Image::class, $examination])
                    <a href="{{ route('examinations.images.create', $examination) }}" class="btn btn--primary">
                        <span class="material-symbols-outlined">upload</span>
                        Upload images
                    </a>
                @endcan
            </div>
        </div>
    </div>

    <div class="viewer-layout">
        <div class="viewer-sidebar custom-scrollbar">
            <div class="viewer-panel">
                <h3 class="viewer-panel__title">Study Information</h3>
                <div class="viewer-meta-list text-body-md">
                    <div class="viewer-meta-row">
                        <span class="text-on-surface-variant">Date</span>
                        <span class="font-data-mono text-on-surface">{{ $examination->date_taken->format('Y-m-d') }}</span>
                    </div>
                    <div class="viewer-meta-row">
                        <span class="text-on-surface-variant">Type</span>
                        <span class="font-data-mono text-on-surface">{{ $examination->examinationType->name }}</span>
                    </div>
                    <div class="viewer-meta-row">
                        <span class="text-on-surface-variant">Body part</span>
                        <span class="font-data-mono text-on-surface">{{ $examination->body_part }}</span>
                    </div>
                    @if ($examination->referring_clinician)
                        <div class="viewer-meta-row">
                            <span class="text-on-surface-variant">Ref. clinician</span>
                            <span class="font-data-mono text-on-surface">{{ $examination->referring_clinician }}</span>
                        </div>
                    @endif
                    @if ($examination->radiographer)
                        <div class="viewer-meta-row">
                            <span class="text-on-surface-variant">Radiographer</span>
                            <span class="font-data-mono text-on-surface">{{ $examination->radiographer }}</span>
                        </div>
                    @endif
                    <div class="viewer-meta-row">
                        <span class="text-on-surface-variant">Referring dept</span>
                        <span class="font-data-mono text-on-surface">{{ $examination->referringDepartment->name }}</span>
                    </div>
                </div>
            </div>

            <div class="viewer-panel">
                <div class="viewer-panel__title-row">
                    <h3 class="viewer-panel__title">Series Gallery</h3>
                    <span class="viewer-gallery-count">{{ $images->count() }} Img</span>
                </div>
                @if ($images->isEmpty())
                    <p class="text-body-md text-on-surface-variant">No images uploaded yet.</p>
                @else
                    <div class="viewer-thumbs">
                        @foreach ($images as $index => $image)
                            <div class="viewer-thumb-wrap">
                                <button
                                    type="button"
                                    class="viewer-thumb {{ $index === 0 ? 'viewer-thumb--active' : '' }}"
                                    data-viewer-thumb
                                    data-image-src="{{ route('images.show', $image) }}"
                                    data-view-label="{{ $image->original_filename }}"
                                    data-download-url="{{ auth()->user()->can('download', $image) ? route('images.download', $image) : '' }}"
                                >
                                    <img src="{{ route('images.thumbnail', $image) }}" alt="{{ $image->original_filename }}" class="viewer-thumb__image">
                                    <span class="viewer-thumb__label font-data-mono">{{ $image->original_filename }}</span>
                                </button>
                                @can('delete', $image)
                                    <a href="{{ route('images.delete', $image) }}" class="viewer-thumb__delete">Delete</a>
                                @endcan
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            @can('send-transfers')
                @if ($sendDestinations->isNotEmpty() && $images->isNotEmpty())
                    <div class="viewer-panel">
                        <h3 class="viewer-panel__title">Send transfer</h3>
                        <form method="POST" action="{{ route('examinations.transfers.store', $examination) }}" class="viewer-transfer-form">
                            @csrf
                            <label class="text-body-md text-on-surface-variant" for="send-departments">Destination departments</label>
                            <select id="send-departments" name="department_ids[]" multiple required class="viewer-transfer-form__select" size="4">
                                @foreach ($sendDestinations as $department)
                                    <option value="{{ $department->id }}">{{ $department->name }}</option>
                                @endforeach
                            </select>
                            <textarea name="message" rows="2" class="viewer-transfer-form__message" placeholder="Optional message"></textarea>
                            <button type="submit" class="btn btn--primary">Send</button>
                        </form>
                    </div>
                @endif
            @endcan

            @can('forward-transfers')
                @if ($inboundTransfer && $forwardDestinations->isNotEmpty())
                    <div class="viewer-panel">
                        <h3 class="viewer-panel__title">Forward transfer</h3>
                        <form method="POST" action="{{ route('transfers.forward', $inboundTransfer) }}" class="viewer-transfer-form">
                            @csrf
                            <label class="text-body-md text-on-surface-variant" for="forward-departments">Destination departments</label>
                            <select id="forward-departments" name="department_ids[]" multiple required class="viewer-transfer-form__select" size="4">
                                @foreach ($forwardDestinations as $department)
                                    <option value="{{ $department->id }}">{{ $department->name }}</option>
                                @endforeach
                            </select>
                            <textarea name="message" rows="2" class="viewer-transfer-form__message" placeholder="Optional message"></textarea>
                            <button type="submit" class="btn btn--secondary">Forward</button>
                        </form>
                    </div>
                @endif
            @endcan

            @if ($outboundTransfers->isNotEmpty())
                <div class="viewer-panel">
                    <h3 class="viewer-panel__title">Sent transfers</h3>
                    <ul class="viewer-transfer-list">
                        @foreach ($outboundTransfers as $outbound)
                            @foreach ($outbound->recipients as $outboundRecipient)
                                <li class="viewer-transfer-list__item">
                                    <span>{{ $outboundRecipient->department?->name }} — {{ $outboundRecipient->status->inboxLabel() }}</span>
                                    @can('recall', $outbound)
                                        @if ($outboundRecipient->status->order() < \App\Enums\TransferRecipientStatus::Acknowledged->order())
                                            <form method="POST" action="{{ route('transfers.recall', $outbound) }}">
                                                @csrf
                                                <input type="hidden" name="department_id" value="{{ $outboundRecipient->department_id }}">
                                                <button type="submit" class="btn btn--ghost">Recall</button>
                                            </form>
                                        @endif
                                    @endcan
                                </li>
                            @endforeach
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <div class="viewer-main" data-viewer-fullscreen-target>
            <div class="viewer-print-header">
                <p class="viewer-print-header__patient">{{ $patient->patient_name }} @if ($patient->nrc) — NRC: {{ $patient->nrc }} @endif</p>
                <p>{{ $examination->examinationType->name }} — {{ $examination->body_part }} · {{ $examination->date_taken->format('Y-m-d') }}</p>
            </div>

            <div class="viewer-toolbar viewer-toolbar--main" data-viewer-toolbar>
                <div class="viewer-toolbar__group">
                    <button type="button" class="viewer-toolbar__btn" data-tool="zoom-out" title="Zoom out">
                        <span class="material-symbols-outlined">zoom_out</span>
                    </button>
                    <span class="viewer-toolbar__zoom-label font-data-mono" data-zoom-label>100%</span>
                    <button type="button" class="viewer-toolbar__btn" data-tool="zoom-in" title="Zoom in">
                        <span class="material-symbols-outlined">zoom_in</span>
                    </button>
                </div>

                <div class="viewer-toolbar__group">
                    <button type="button" class="viewer-toolbar__btn" data-tool="rotate" title="Rotate 90°">
                        <span class="material-symbols-outlined">rotate_90_degrees_cw</span>
                    </button>
                </div>

                <div class="viewer-toolbar__group viewer-toolbar__group--sliders">
                    <label class="viewer-toolbar__slider">
                        <span class="material-symbols-outlined" title="Brightness">brightness_6</span>
                        <input type="range" min="50" max="150" step="5" value="100" data-tool="brightness" aria-label="Brightness">
                    </label>
                    <label class="viewer-toolbar__slider">
                        <span class="material-symbols-outlined" title="Contrast">contrast</span>
                        <input type="range" min="50" max="150" step="5" value="100" data-tool="contrast" aria-label="Contrast">
                    </label>
                </div>

                <div class="viewer-toolbar__group">
                    <button type="button" class="viewer-toolbar__btn" data-tool="invert" title="Invert">
                        <span class="material-symbols-outlined">invert_colors</span>
                    </button>
                    <button type="button" class="viewer-toolbar__btn" data-tool="measure" title="Measure">
                        <span class="material-symbols-outlined">straighten</span>
                    </button>
                    <button type="button" class="viewer-toolbar__btn" data-tool="compare" title="Compare">
                        <span class="material-symbols-outlined">compare</span>
                    </button>
                    <button type="button" class="viewer-toolbar__btn" data-tool="fullscreen" title="Fullscreen">
                        <span class="material-symbols-outlined">fullscreen</span>
                    </button>
                    <button type="button" class="viewer-toolbar__btn" data-tool="reset" title="Reset view">
                        <span class="material-symbols-outlined">restart_alt</span>
                    </button>
                </div>

                <div class="viewer-toolbar__group viewer-toolbar__group--actions">
                    <a href="{{ $canDownloadActive ? route('images.download', $activeImage) : '#' }}" class="viewer-toolbar__btn" data-tool="download" title="Download" @if (! $canDownloadActive) aria-disabled="true" @endif>
                        <span class="material-symbols-outlined">download</span>
                    </a>
                    <button type="button" class="viewer-toolbar__btn" data-tool="print" title="Print">
                        <span class="material-symbols-outlined">print</span>
                    </button>
                </div>
            </div>

            <div class="viewer-canvas">
                <div class="viewer-stage" data-viewer-stage>
                    <div class="viewer-pane viewer-pane--focused" data-viewer-pane="a">
                        <div class="viewer-pane__viewport" data-pane-viewport>
                            <img
                                src="{{ $mainImageUrl }}"
                                alt="{{ $examination->examinationType->name }} {{ $examination->body_part }}"
                                class="viewer-pane__image"
                                data-pane-image
                                data-download-url="{{ $canDownloadActive ? route('images.download', $activeImage) : '' }}"
                            >
                            <svg class="viewer-pane__measure-layer" data-pane-measure-layer></svg>
                        </div>
                        <div class="viewer-overlay viewer-overlay--top-left font-data-mono">
                            <p>{{ $patient->patient_name }}</p>
                            @if ($patient->nrc)
                                <p>NRC: {{ $patient->nrc }}</p>
                            @endif
                        </div>
                        <div class="viewer-overlay viewer-overlay--top-right font-data-mono">
                            <p>Chililabombwe District</p>
                            <p>Study: {{ $examination->date_taken->format('Y-m-d') }}</p>
                            <p data-pane-view-label>{{ $viewLabel }}</p>
                        </div>
                    </div>

                    <div class="viewer-pane viewer-pane--empty" data-viewer-pane="b" hidden>
                        <div class="viewer-pane__viewport" data-pane-viewport>
                            <img src="" alt="" class="viewer-pane__image" data-pane-image hidden>
                            <svg class="viewer-pane__measure-layer" data-pane-measure-layer></svg>
                            <div class="viewer-pane__placeholder" data-pane-placeholder>
                                <span class="material-symbols-outlined">compare</span>
                                <p>Select an image from the gallery to compare</p>
                            </div>
                        </div>
                        <div class="viewer-overlay viewer-overlay--top-right font-data-mono">
                            <p data-pane-view-label></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
