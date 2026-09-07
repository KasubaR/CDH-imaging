@extends('layouts.app')

@section('title', 'Delete image permanently')
@section('topbar_desktop_title', 'Delete image')

@section('content')
@php
    $patient = $examination?->patient;
@endphp

<div class="canvas__inner image-delete-page">
    <div class="page-header">
        <h2 class="text-headline-lg text-on-surface">Permanently delete image</h2>
        <p class="text-body-md page-header__lede">
            This cannot be undone. The original file and thumbnail will be removed from storage.
        </p>
    </div>

    <div class="bento-card image-delete-card">
        <div class="card-header">
            <h3 class="text-headline-sm text-on-surface">Confirm deletion</h3>
            @if ($examination)
                <a href="{{ route('examinations.show', $examination) }}" class="btn btn--secondary">
                    <span class="material-symbols-outlined">arrow_back</span>
                    Cancel
                </a>
            @endif
        </div>

        <div class="card-body upload-context">
            @if ($patient)
                <div class="upload-context__row">
                    <span class="upload-context__label">Patient</span>
                    <span class="upload-context__value">{{ $patient->patient_name }}</span>
                </div>
            @endif
            @if ($examination)
                <div class="upload-context__row">
                    <span class="upload-context__label">Examination</span>
                    <span class="upload-context__value">
                        {{ $examination->examinationType->name }} — {{ $examination->body_part }}
                    </span>
                </div>
            @endif
            <div class="upload-context__row">
                <span class="upload-context__label">Image ID</span>
                <span class="upload-context__value font-data-mono">{{ $image->uuid }}</span>
            </div>
            <div class="upload-context__row">
                <span class="upload-context__label">Filename</span>
                <span class="upload-context__value">{{ $image->original_filename }}</span>
            </div>
        </div>

        <div class="card-body image-delete-form-body">
            <form method="post" action="{{ route('images.destroy', $image) }}" class="image-delete-form">
                @csrf
                @method('DELETE')

                <label class="image-delete-form__label text-body-md" for="confirmation">
                    Type <span class="font-data-mono">PERMANENTLY DELETE</span> to confirm
                </label>
                <input
                    id="confirmation"
                    name="confirmation"
                    type="text"
                    class="image-delete-form__input"
                    autocomplete="off"
                    required
                    value="{{ old('confirmation') }}"
                >

                @error('confirmation')
                    <p class="image-delete-form__error text-body-md">{{ $message }}</p>
                @enderror

                <div class="image-delete-form__actions">
                    @if ($examination)
                        <a href="{{ route('examinations.show', $examination) }}" class="btn btn--secondary">Cancel</a>
                    @endif
                    <button type="submit" class="btn btn--primary image-delete-form__submit">
                        PERMANENTLY DELETE
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
