<?php

use Illuminate\Http\Request;
use Illuminate\Support\Str;
// use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// logout
Route::get('/logout', function () {
    if (auth()->check()) {
        auth()->logout();
        return redirect('/login');
    }

    throw new Exception('User not logged in');
});

Route::middleware([
    'auth_oauth.nanicas',
])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::get('/redirect', function (Request $request) {
    $state = Str::random(40);
    // $request->session()->put('state', $state);

    $query = http_build_query([
        'client_id' => 10,
        'redirect_uri' => 'http://tatame_mvp.local.com:8010/callback',
        'response_type' => 'code',
        'scope' => '',
        'state' => $state,
        'prompt' => 'consent', // "none", "consent", or "login"
    ]);

    return redirect('http://authentication.local.com:8002/oauth/authorize?' . $query);
});

require __DIR__ . '/auth.php';
