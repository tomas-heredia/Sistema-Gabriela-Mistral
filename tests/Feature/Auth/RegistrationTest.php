<?php

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\Route;

test('el registro publico esta cerrado', function () {
    expect(Route::has('register'))->toBeFalse();

    $response = $this->get('/register');

    $response->assertNotFound();
});
