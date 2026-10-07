<?php

use App\Http\Controllers\Activities\ActivityController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\Clients\ClientController;
use App\Http\Controllers\Clients\ClientStatusController;
use App\Http\Controllers\Contacts\ContactController;
use App\Http\Controllers\Contacts\PrimaryContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Opportunities\OpportunityController;
use App\Http\Controllers\Opportunities\OpportunityStatusController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Prospects\ProspectController;
use App\Http\Controllers\Prospects\ProspectConversionController;
use App\Http\Controllers\Prospects\ProspectStatusController;
use App\Http\Controllers\Quotes\QuoteController;
use App\Http\Controllers\Quotes\QuotePdfController;
use App\Http\Controllers\Quotes\QuoteStatusController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\Roles\RoleController;
use App\Http\Controllers\Users\UserController;
use App\Http\Controllers\Users\UserStatusController;
use App\Models\Activity;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\Prospect;
use App\Models\Quote;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

// Autenticación

Route::middleware(['auth', 'active', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');

    // Usuarios
    Route::prefix('users')->name('users.')->group(function () {
        Route::get('/', [UserController::class, 'index'])
            ->middleware(Authorize::using('viewAny', User::class))
            ->name('index');

        Route::get('/create', [UserController::class, 'create'])
            ->middleware(Authorize::using('create', User::class))
            ->name('create');

        Route::post('/', [UserController::class, 'store'])->name('store');

        Route::get('/{user}', [UserController::class, 'show'])
            ->middleware(Authorize::using('view', 'user'))
            ->name('show');

        Route::get('/{user}/edit', [UserController::class, 'edit'])
            ->middleware(Authorize::using('update', 'user'))
            ->name('edit');

        Route::match(['put', 'patch'], '/{user}', [UserController::class, 'update'])->name('update');

        Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');

        Route::patch('/{user}/status/{status}', UserStatusController::class)
            ->middleware(Authorize::using('changeStatus', 'user'))
            ->whereIn('status', ['activate', 'deactivate'])
            ->name('status');
    });

    // Roles y permisos
    Route::prefix('roles')->name('roles.')->group(function () {
        Route::get('/', [RoleController::class, 'index'])
            ->middleware(Authorize::using('viewAny', Role::class))
            ->name('index');

        Route::get('/create', [RoleController::class, 'create'])
            ->middleware(Authorize::using('create', Role::class))
            ->name('create');

        Route::post('/', [RoleController::class, 'store'])->name('store');

        Route::get('/{role}', [RoleController::class, 'show'])
            ->middleware(Authorize::using('view', 'role'))
            ->name('show');

        Route::get('/{role}/edit', [RoleController::class, 'edit'])
            ->middleware(Authorize::using('update', 'role'))
            ->name('edit');

        Route::match(['put', 'patch'], '/{role}', [RoleController::class, 'update'])->name('update');

        Route::delete('/{role}', [RoleController::class, 'destroy'])->name('destroy');
    });

    // Clientes
    Route::prefix('clients')->name('clients.')->group(function () {
        Route::get('/', [ClientController::class, 'index'])
            ->middleware(Authorize::using('viewAny', Client::class))
            ->name('index');

        Route::get('/create', [ClientController::class, 'create'])
            ->middleware(Authorize::using('create', Client::class))
            ->name('create');

        Route::post('/', [ClientController::class, 'store'])->name('store');

        Route::get('/{client}', [ClientController::class, 'show'])
            ->middleware(Authorize::using('view', 'client'))
            ->name('show');

        Route::get('/{client}/edit', [ClientController::class, 'edit'])
            ->middleware(Authorize::using('update', 'client'))
            ->name('edit');

        Route::match(['put', 'patch'], '/{client}', [ClientController::class, 'update'])->name('update');

        Route::delete('/{client}', [ClientController::class, 'destroy'])
            ->middleware(Authorize::using('delete', 'client'))
            ->name('destroy');

        Route::patch('/{client}/status', ClientStatusController::class)
            ->middleware(Authorize::using('changeStatus', 'client'))
            ->name('status');

        // Contactos
        Route::get('/{client}/contacts/create', [ContactController::class, 'create'])
            ->name('contacts.create');

        Route::post('/{client}/contacts', [ContactController::class, 'store'])->name('contacts.store');
    });

    Route::prefix('contacts')->name('contacts.')->group(function () {
        Route::get('/', [ContactController::class, 'index'])
            ->middleware(Authorize::using('viewAny', Contact::class))
            ->name('index');

        Route::get('/{contact}/edit', [ContactController::class, 'edit'])
            ->middleware(Authorize::using('update', 'contact'))
            ->name('edit');

        Route::match(['put', 'patch'], '/{contact}', [ContactController::class, 'update'])->name('update');

        Route::delete('/{contact}', [ContactController::class, 'destroy'])
            ->middleware(Authorize::using('delete', 'contact'))
            ->name('destroy');

        Route::patch('/{contact}/primary', PrimaryContactController::class)
            ->middleware(Authorize::using('setPrimary', 'contact'))
            ->name('primary');
    });

    // Oportunidades
    Route::prefix('opportunities')->name('opportunities.')->group(function () {
        Route::get('/', [OpportunityController::class, 'index'])
            ->middleware(Authorize::using('viewAny', Opportunity::class))
            ->name('index');

        Route::get('/create', [OpportunityController::class, 'create'])
            ->middleware(Authorize::using('create', Opportunity::class))
            ->name('create');

        Route::post('/', [OpportunityController::class, 'store'])->name('store');

        Route::get('/{opportunity}', [OpportunityController::class, 'show'])
            ->middleware(Authorize::using('view', 'opportunity'))
            ->name('show');

        Route::get('/{opportunity}/edit', [OpportunityController::class, 'edit'])
            ->middleware(Authorize::using('update', 'opportunity'))
            ->name('edit');

        Route::match(['put', 'patch'], '/{opportunity}', [OpportunityController::class, 'update'])->name('update');

        Route::delete('/{opportunity}', [OpportunityController::class, 'destroy'])
            ->middleware(Authorize::using('delete', 'opportunity'))
            ->name('destroy');

        Route::patch('/{opportunity}/status/{status}', OpportunityStatusController::class)
            ->middleware(Authorize::using('changeStatus', 'opportunity'))
            ->whereIn('status', array_keys(config('opportunities.statuses')))
            ->name('status');
    });

    // Actividades
    Route::prefix('activities')->name('activities.')->group(function () {
        Route::get('/', [ActivityController::class, 'index'])
            ->middleware(Authorize::using('viewAny', Activity::class))
            ->name('index');

        Route::get('/create', [ActivityController::class, 'create'])
            ->middleware(Authorize::using('create', Activity::class))
            ->name('create');

        Route::post('/', [ActivityController::class, 'store'])->name('store');

        Route::get('/{activity}', [ActivityController::class, 'show'])
            ->middleware(Authorize::using('view', 'activity'))
            ->name('show');

        Route::get('/{activity}/edit', [ActivityController::class, 'edit'])
            ->middleware(Authorize::using('update', 'activity'))
            ->name('edit');

        Route::match(['put', 'patch'], '/{activity}', [ActivityController::class, 'update'])->name('update');

        Route::delete('/{activity}', [ActivityController::class, 'destroy'])
            ->middleware(Authorize::using('delete', 'activity'))
            ->name('destroy');
    });

    // Calendario
    Route::get('/calendar', [CalendarController::class, 'index'])
        ->middleware(Authorize::using('viewAny', Activity::class))
        ->name('calendar.index');

    // Reportes
    Route::get('/reports', [ReportsController::class, 'index'])
        ->middleware('can:reports.view')
        ->name('reports.index');

    // Prospectos
    Route::prefix('prospects')->name('prospects.')->group(function () {
        Route::get('/', [ProspectController::class, 'index'])
            ->middleware(Authorize::using('viewAny', Prospect::class))
            ->name('index');

        Route::get('/create', [ProspectController::class, 'create'])
            ->middleware(Authorize::using('create', Prospect::class))
            ->name('create');

        Route::post('/', [ProspectController::class, 'store'])->name('store');

        Route::get('/{prospect}', [ProspectController::class, 'show'])
            ->middleware(Authorize::using('view', 'prospect'))
            ->name('show');

        Route::get('/{prospect}/edit', [ProspectController::class, 'edit'])
            ->middleware(Authorize::using('update', 'prospect'))
            ->name('edit');

        Route::match(['put', 'patch'], '/{prospect}', [ProspectController::class, 'update'])->name('update');

        Route::delete('/{prospect}', [ProspectController::class, 'destroy'])
            ->middleware(Authorize::using('delete', 'prospect'))
            ->name('destroy');

        Route::patch('/{prospect}/status/{status}', ProspectStatusController::class)
            ->middleware(Authorize::using('changeStatus', 'prospect'))
            ->whereIn('status', config('prospects.qualified_statuses'))
            ->name('status');

        // Conversión
        Route::get('/{prospect}/convert', [ProspectConversionController::class, 'create'])
            ->middleware(Authorize::using('convert', 'prospect'))
            ->name('convert.create');

        Route::post('/{prospect}/convert', [ProspectConversionController::class, 'store'])
            ->name('convert.store');
    });

    // Cotizaciones
    Route::prefix('quotes')->name('quotes.')->group(function () {
        Route::get('/', [QuoteController::class, 'index'])
            ->middleware(Authorize::using('viewAny', Quote::class))
            ->name('index');

        Route::get('/create', [QuoteController::class, 'create'])
            ->middleware(Authorize::using('create', Quote::class))
            ->name('create');

        Route::post('/', [QuoteController::class, 'store'])->name('store');

        Route::get('/{quote}', [QuoteController::class, 'show'])
            ->middleware(Authorize::using('view', 'quote'))
            ->name('show');

        Route::get('/{quote}/edit', [QuoteController::class, 'edit'])
            ->middleware(Authorize::using('update', 'quote'))
            ->name('edit');

        Route::match(['put', 'patch'], '/{quote}', [QuoteController::class, 'update'])->name('update');

        Route::delete('/{quote}', [QuoteController::class, 'destroy'])
            ->middleware(Authorize::using('delete', 'quote'))
            ->name('destroy');

        Route::patch('/{quote}/status/{status}', QuoteStatusController::class)
            ->middleware(Authorize::using('changeStatus', 'quote'))
            ->whereIn('status', array_keys(config('quotes.statuses')))
            ->name('status');

        Route::get('/{quote}/pdf', QuotePdfController::class)
            ->middleware(Authorize::using('pdf', 'quote'))
            ->name('pdf');
    });
});
