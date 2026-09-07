@extends('layouts.app')

@section('title', 'Settings — Chililabombwe District Hospital')
@section('topbar_desktop_title', 'Settings')

@section('content')
<div class="canvas__inner admin-settings-page">
    <div class="page-header">
        <h2 class="text-headline-lg text-on-surface">Settings</h2>
        <p class="text-body-md page-header__lede">Storage and retention limits. Changes take effect immediately — no restart or deploy needed.</p>
    </div>

    <div class="bento-card admin-settings-storage">
        <div class="card-header">
            <h3 class="text-headline-sm text-on-surface">Storage</h3>
        </div>
        <div class="card-body admin-settings-storage__body">
            <span class="admin-settings-storage__label">Images on disk</span>
            <span class="admin-settings-storage__value font-data-mono">{{ $storageUsed }}</span>
        </div>
    </div>

    <div class="bento-card form-card">
        <form method="post" action="{{ route('admin.settings.update') }}" class="form-stack">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label class="form-label" for="image_retention_months">Image retention (months)</label>
                <input
                    type="number" id="image_retention_months" name="image_retention_months"
                    class="form-input" min="1" max="120"
                    value="{{ old('image_retention_months', $values['image_retention_months']) }}" required
                >
                <p class="form-hint">Images older than this (by upload time) are permanently deleted by the daily retention purge.</p>
            </div>

            <div class="form-group">
                <label class="form-label" for="image_max_mb">Max image size (MB)</label>
                <input
                    type="number" id="image_max_mb" name="image_max_mb"
                    class="form-input" min="1" max="100"
                    value="{{ old('image_max_mb', $values['image_max_mb']) }}" required
                >
                <p class="form-hint">The largest single X-ray image accepted, enforced on both the direct and chunked upload paths.</p>
            </div>

            <div class="form-group">
                <label class="form-label" for="upload_session_ttl_hours">Incomplete upload lifetime (hours)</label>
                <input
                    type="number" id="upload_session_ttl_hours" name="upload_session_ttl_hours"
                    class="form-input" min="1" max="168"
                    value="{{ old('upload_session_ttl_hours', $values['upload_session_ttl_hours']) }}" required
                >
                <p class="form-hint">Abandoned chunked uploads older than this are purged hourly, freeing their partial files.</p>
            </div>

            <div class="form-group">
                <label class="form-label" for="backup_retention_days">Backup retention (days)</label>
                <input
                    type="number" id="backup_retention_days" name="backup_retention_days"
                    class="form-input" min="1" max="365"
                    value="{{ old('backup_retention_days', $values['backup_retention_days']) }}" required
                >
                <p class="form-hint">Encrypted database and image backups older than this are deleted daily.</p>
            </div>

            <div class="form-stack__submit">
                <button type="submit" class="btn btn--primary">Save settings</button>
            </div>
        </form>
    </div>
</div>
@endsection
