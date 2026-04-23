<?php

declare(strict_types=1);

use App\Data\Sau\PrssLoginR01Dto;
use App\Data\Sau\PrssLoginR01ResultDto;
use App\Http\Controllers\Auth\OtpzController;
use App\Http\Controllers\Flows\FlowCatalogController;
use App\Http\Controllers\Flows\FlowIntakeController;
use App\Http\Controllers\Flows\Intake\NegotiationArtifactsController;
use App\Http\Controllers\Flows\Intake\OverrideRequestController;
use App\Http\Controllers\Flows\Intake\StudentSearchController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\SybasePingController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserEmailResetNotificationController;
use App\Http\Controllers\UserEmailVerificationController;
use App\Http\Controllers\UserEmailVerificationNotificationController;
use App\Http\Controllers\UserListController;
use App\Http\Controllers\UserPasswordController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\UserTwoFactorAuthenticationController;
use App\Http\Controllers\WorkflowApprovalController;
use App\Http\Controllers\WorkflowFormController;
use App\Http\Middleware\RepairHtmlEntityAmpersandsInQueryString;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/__sybase-ping', function () {

    // $resultset = DB::connection('sau')->select(
    //     'prss_login_r01 @cd_pessoa_p = ?, @tp_cd_pessoa = ?, @senha = ?',
    //     config('sau.prss_login', []),
    // );
    // return response()->json($resultset, 200, [], JSON_UNESCAPED_UNICODE);

    // // Teste simples de select no Sybase para verificar se está funcionando
    // $resultset = DB::connection('sau')
    //     ->select('select 1 as teste');
    // return response()->json($resultset, 200, [], JSON_UNESCAPED_UNICODE);

    // Get credentials from config
    ['credentialId' => $credentialId, 'credentialType' => $credentialType, 'password' => $password] = config('sau.prss_login', []);

    // Create credentials object
    $credentials = new PrssLoginR01Dto(
        credentialId: (string) $credentialId,
        credentialType: (string) $credentialType,
        password: (string) $password,
    );

    /** @var Collection<int, PrssLoginR01ResultDto> $resultset */
    $resultset = DB::connection('sau')
        ->rpc('prss_login_r01')
        ->with($credentials)
        ->throwOnError()
        ->getAs(PrssLoginR01ResultDto::class);

    // var_dump($resultset[0]);
    return response()->json($resultset, 200, [], JSON_UNESCAPED_UNICODE);

})->name('debug.sybase-ping');

Route::get('/sybase', SybasePingController::class)->name('debug.sybase');

Route::get('/', fn () => Inertia::render('welcome'))->name('home');

Route::post('workflow-approvals/{run}/{node}/{token}/submit', [WorkflowApprovalController::class, 'submit'])
    ->middleware([RepairHtmlEntityAmpersandsInQueryString::class, 'signed'])
    ->name('workflow-approvals.submit');

Route::get('workflow-approvals/{run}/{node}/{token}/{decision}', [WorkflowApprovalController::class, 'fallback'])
    ->middleware([RepairHtmlEntityAmpersandsInQueryString::class, 'signed'])
    ->where('decision', 'approve|reject')
    ->name('workflow-approvals.fallback');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('dashboard', fn () => Inertia::render('dashboard'))->name('dashboard');
    Route::redirect('matricular', '/flows');
    Route::get('flows', [FlowCatalogController::class, 'index'])->name('flows.index');
    Route::get('flows/new', [FlowIntakeController::class, 'create'])->name('flows.intake');
    Route::get('flows/intake/students', StudentSearchController::class)->name('flows.intake.students.search');
    Route::post('flows/intake/negotiations', NegotiationArtifactsController::class)->name('flows.intake.negotiations.store');
    Route::post('flows/intake/overrides', OverrideRequestController::class)->name('flows.intake.overrides.store');
    Route::get('flows/runs/{run}', [FlowCatalogController::class, 'show'])->name('flows.runs.show');
    Route::post('flows/{workflow}/runs', [FlowCatalogController::class, 'store'])->name('flows.runs.store');
    Route::get('users', [UserListController::class, 'index'])->name('users.index');
    Route::impersonate();

    Route::patch('workflow-forms/preferences', [WorkflowFormController::class, 'preferences'])
        ->name('workflow-forms.preferences');
    Route::get('workflow-forms/{token}', [WorkflowFormController::class, 'show'])
        ->name('workflow-forms.show');
    Route::post('workflow-forms/{token}', [WorkflowFormController::class, 'submit'])
        ->name('workflow-forms.submit');
    Route::post('workflow-forms/{token}/submit-chat', [WorkflowFormController::class, 'submitChat'])
        ->name('workflow-forms.submit-chat');
    Route::post('workflow-forms/{token}/chat', [WorkflowFormController::class, 'chat'])
        ->name('workflow-forms.chat');
    Route::post('workflow-forms/{token}/chat/edit', [WorkflowFormController::class, 'edit'])
        ->name('workflow-forms.chat.edit');
    Route::post('workflow-forms/{token}/ai-extract', [WorkflowFormController::class, 'aiExtract'])
        ->name('workflow-forms.ai-extract');
    Route::post('workflow-forms/{token}/ai-copilot', [WorkflowFormController::class, 'aiCopilot'])
        ->name('workflow-forms.ai-copilot');
});

Route::middleware('auth')->group(function (): void {
    // User...
    Route::delete('user', [UserController::class, 'destroy'])->name('user.destroy');

    // User Profile...
    Route::redirect('settings', '/settings/profile');
    Route::get('settings/profile', [UserProfileController::class, 'edit'])->name('user-profile.edit');
    Route::patch('settings/profile', [UserProfileController::class, 'update'])->name('user-profile.update');

    // User Password...
    Route::get('settings/password', [UserPasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [UserPasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('password.update');

    // Appearance...
    Route::get('settings/appearance', fn () => Inertia::render('appearance/update'))->name('appearance.edit');

    // User Two-Factor Authentication...
    Route::get('settings/two-factor', [UserTwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');
});

Route::middleware('guest')->group(function (): void {
    // User...
    Route::get('register', [UserController::class, 'create'])
        ->name('register');
    Route::post('register', [UserController::class, 'store'])
        ->name('register.store');

    // User Password...
    Route::get('reset-password/{token}', [UserPasswordController::class, 'create'])
        ->name('password.reset');
    Route::post('reset-password', [UserPasswordController::class, 'store'])
        ->name('password.store');

    // User Email Reset Notification...
    Route::get('forgot-password', [UserEmailResetNotificationController::class, 'create'])
        ->name('password.request');
    Route::post('forgot-password', [UserEmailResetNotificationController::class, 'store'])
        ->name('password.email');

    // Session...
    Route::get('login', [SessionController::class, 'create'])
        ->name('login');
    Route::post('login', [SessionController::class, 'store'])
        ->name('login.store');

    Route::get('otpz', [OtpzController::class, 'index'])->name('otpz.index');
    Route::post('otpz', [OtpzController::class, 'store'])->name('otpz.store');
    Route::get('otpz/{id}', [OtpzController::class, 'show'])->name('otpz.show')->middleware('signed');
    Route::post('otpz/{id}', [OtpzController::class, 'verify'])->name('otpz.verify')->middleware('signed');
});

Route::middleware('auth')->group(function (): void {
    // User Email Verification...
    Route::get('verify-email', [UserEmailVerificationNotificationController::class, 'create'])
        ->name('verification.notice');
    Route::post('email/verification-notification', [UserEmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    // User Email Verification...
    Route::get('verify-email/{id}/{hash}', [UserEmailVerificationController::class, 'update'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    // Session...
    Route::post('logout', [SessionController::class, 'destroy'])
        ->name('logout');
});
