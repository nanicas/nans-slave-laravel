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

Route::get('/redirect', function (Request $request) {
    $request->session()->put('state', $state = Str::random(40));

    $request->session()->put(
        'code_verifier',
        $code_verifier = Str::random(128)
    );

    $codeChallenge = strtr(rtrim(
        base64_encode(hash('sha256', $code_verifier, true)),
        '='
    ), '+/', '-_');

    $config = config('nanicas_auth');

    $client_id = $config['AUTHENTICATION_CLIENT_ID_PUBLIC'];

    $query = http_build_query([
        'client_id' => $client_id,
        'redirect_uri' => env('APPLICATION_CALLBACK_URL'),
        'response_type' => 'code',
        'prompt' => 'consent',
        'scope' => '',
        'state' => $state,
        'code_challenge' => $codeChallenge,
        'code_challenge_method' => 'S256',
    ]);

    return redirect($config['AUTHENTICATION_API_URL_PUBLIC'] . 'oauth/authorize?' . $query);
})->name('redirect.slave');

Route::get('callback', function (Request $request) {
    $state = $request->session()->pull('state');

    $codeVerifier = $request->session()->pull('code_verifier');

    throw_unless(
        strlen($state) > 0 && $state === $request->state,
        InvalidArgumentException::class
    );

    $config = config('nanicas_auth');

    $code = $request->code;
    $response = Http::asForm()->post($config['AUTHENTICATION_API_URL'] . 'oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $config['AUTHENTICATION_CLIENT_ID_PUBLIC'],
        'redirect_uri' => env('APPLICATION_CALLBACK_URL'),
        'code_verifier' => $codeVerifier,
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

Route::get('/redirect/camaleao', function () {
    return redirect(env('THIRDY_APPLICATION_REDIRECT_URL'));
})->name('redirect.camaleao');

require __DIR__ . '/auth.php';
