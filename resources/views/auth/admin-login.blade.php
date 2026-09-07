@extends('layouts.guest')

@section('title', 'Admin sign in — Chililabombwe District Hospital')

@section('content')
<div class="page-header">
    <h2 class="text-headline-md text-on-surface">Admin sign in</h2>
    <p class="text-body-md page-header__lede">System administrator access for Chililabombwe District Hospital.</p>
</div>

<form method="post" action="{{ route('admin.login') }}" class="form-stack">
    @csrf

    <div class="form-group">
        <label class="form-label" for="username">Username</label>
        <div class="form-input-group">
            <span class="material-symbols-outlined form-input-group__icon">person</span>
            <input type="text" id="username" name="username" class="form-input" value="{{ old('username') }}" required autocomplete="username" autofocus>
        </div>
    </div>

    <div class="form-group">
        <label class="form-label" for="password">Password</label>
        <div class="form-input-group form-input-group--has-toggle">
            <span class="material-symbols-outlined form-input-group__icon">lock</span>
            <input type="password" id="password" name="password" class="form-input" required autocomplete="current-password" data-password-input>
            <button type="button" class="form-input-group__toggle" data-toggle-password="password" aria-label="Show password" aria-pressed="false">
                <span class="material-symbols-outlined">visibility</span>
            </button>
        </div>
    </div>

    <div class="form-row">
        <label class="form-checkbox">
            <input type="checkbox" name="remember">
            Remember me
        </label>
    </div>

    <div class="form-stack__submit">
        <button type="submit" class="btn btn--primary btn--full">Sign in</button>
    </div>
</form>

<p class="auth-card__footer">Department staff? <a href="{{ route('login') }}" class="btn--link">Sign in here</a></p>
@endsection
