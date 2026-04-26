<?php

use Illuminate\Http\Request;
use Illuminate\Support\Str;
// use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Http;
use Nanicas\Auth\Contracts\AuthenticationClient;
use Illuminate\Support\Facades\Auth;
use Nanicas\Auth\Frameworks\Laravel\Helpers\AuthHelper;
use App\Models\User;

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

Route::get('/logout', function () {
    if (auth()->check()) {
        auth()->logout();
        return redirect('/login');
    }

    throw new Exception('User not logged in');
});

Route::middleware([
    // 'define_contract_by_domain.nanicas',
    'auth_oauth.nanicas',
    // 'authorizate_request_user_session.nanicas',
])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::get('/redirect', function(Request $request) {
    $request->session()->put('state', $state = Str::random(40));

    $client_id = config('nanicas_auth')['AUTHENTICATION_CLIENT_ID'];
    $redirect_uri = 'http://slave.local.com:8011/callback';

    $query = http_build_query([
        'client_id' => $client_id,
        'redirect_uri' => $redirect_uri,
        'response_type' => 'code',
        'prompt' => 'consent',
        'scope' => '',
        'state' => $state,
    ]);

    return redirect('http://authentication.local.com:8002/oauth/authorize?' . $query);
})->name('redirect.slave');

Route::get('callback', function (Request $request) {
    if ($request->state !== session('state')) {
        abort(403, 'Invalid state');
    }

    $code = $request->code;
    $response = Http::asForm()->post(config('nanicas_auth')['AUTHENTICATION_API_URL'] . 'oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => config('nanicas_auth')['AUTHENTICATION_CLIENT_ID'],
        'client_secret' => config('nanicas_auth')['AUTHENTICATION_CLIENT_SECRET'],
        'redirect_uri' => 'http://slave.local.com:8011/callback',
        'code' => $code,
    ]);

    $authService = app()->make(AuthenticationClient::class);
    $userResponse = $authService->retrieveByToken($response->json()['access_token']);
    $user = new User($userResponse['body']);
    $user->exists = true;

    Auth::login($user);
    AuthHelper::putAuthInfoInSession(
        session(),
        $response->json()
    );

    return redirect('/dashboard');
})->name('callback');

Route::get('/redirect/camaleao', function() {
    return redirect('http://camaleao.local.com:8000/redirect');
})->name('redirect.camaleao');

require __DIR__ . '/auth.php';
