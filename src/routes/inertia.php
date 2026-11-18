<?php

use Laravel\Nova\Http\Requests\NovaRequest;

Route::get('/', function (NovaRequest $request) {
    return inertia('SystemSettings');
});
