<?php

namespace App\Http\Controllers\Users;

use App\Actions\Users\ChangeUserStatus;
use App\Actions\Users\CreateUser;
use App\Actions\Users\DeleteUser;
use App\Actions\Users\SyncUserRoles;
use App\Actions\Users\UpdateUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\DeleteUserRequest;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(
        private readonly CreateUser $createUser,
        private readonly UpdateUser $updateUser,
        private readonly DeleteUser $deleteUser,
        private readonly ChangeUserStatus $changeUserStatus,
        private readonly SyncUserRoles $syncUserRoles,
    ) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', '');

        $users = User::query()
            ->with('roles:id,name')
            ->when($search !== '', fn ($query) => $query->where(
                fn ($q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
            ))
            ->when(in_array($status, ['active', 'inactive'], true), fn ($query) => $query->where(
                'is_active',
                $status === 'active',
            ))
            ->orderBy('name')
            ->paginate(config('crm.per_page'))
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->isActive(),
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'created_at' => $user->created_at->toIso8601String(),
                'roles' => $user->roles->map(fn (Role $role): array => [
                    'id' => $role->id,
                    'name' => $role->name,
                ])->values()->all(),
            ]);

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'can' => [
                'create' => $request->user()->can('create', User::class),
                'update' => $request->user()->can('update', User::class),
                'changeStatus' => $request->user()->can('changeStatus', User::class),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Users/Create', [
            'roles' => $this->roleOptions(),
            'can' => [
                'assignRoles' => $request->user()->can('assignRoles', User::class),
            ],
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = $this->createUser->handle([
            ...$request->safe()->all(['name', 'email', 'password', 'is_active']),
            'email_verified' => $request->boolean('email_verified'),
        ], $request->has('roles') ? $request->validated('roles', []) : null);

        return redirect()
            ->route('users.show', $user)
            ->with('success', __('users.created', ['name' => $user->name]));
    }

    public function show(Request $request, User $user): Response
    {
        $user->load('roles:id,name');

        return Inertia::render('Users/Show', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->isActive(),
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'created_at' => $user->created_at->toIso8601String(),
                'updated_at' => $user->updated_at->toIso8601String(),
                'roles' => $user->roles->map(fn (Role $role): array => [
                    'id' => $role->id,
                    'name' => $role->name,
                ])->values()->all(),
            ],
            'roles' => $this->roleOptions(),
            'can' => [
                'update' => $request->user()->can('update', $user),
                'delete' => $request->user()->can('delete', $user),
                'changeStatus' => $request->user()->can('changeStatus', $user),
                'assignRoles' => $request->user()->can('assignRoles', $user),
                'viewRoles' => $request->user()->can('viewAny', Role::class),
            ],
        ]);
    }

    public function edit(Request $request, User $user): Response
    {
        $user->load('roles:id,name');

        return Inertia::render('Users/Edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->isActive(),
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'roles' => $user->roles->pluck('id')->all(),
            ],
            'roles' => $this->roleOptions(),
            'can' => [
                'assignRoles' => $request->user()->can('assignRoles', $user),
            ],
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = [
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'email_verified' => $request->boolean('email_verified'),
            'password' => $request->validated('password'),
        ];

        // El estado sigue pasando por ChangeUserStatus, para que apliquen
        // siempre las reglas que impiden dejar a todos fuera del sistema.
        if ($request->has('is_active') && $request->boolean('is_active') !== $user->isActive()) {
            try {
                $this->changeUserStatus->handle(
                    $request->user(),
                    $user,
                    $request->boolean('is_active'),
                );
            } catch (DomainException $exception) {
                return back()->with('error', $exception->getMessage());
            }
        }

        if ($request->has('roles')) {
            try {
                $this->syncUserRoles->handle(
                    $request->user(),
                    $user,
                    $request->validated('roles', []),
                );
            } catch (DomainException $exception) {
                return back()->with('error', $exception->getMessage());
            }
        }

        $this->updateUser->handle($user, $data);

        return redirect()
            ->route('users.show', $user)
            ->with('success', __('users.updated', ['name' => $user->name]));
    }

    public function destroy(DeleteUserRequest $request, User $user): RedirectResponse
    {
        $name = $user->name;

        try {
            $this->deleteUser->handle($request->user(), $user);
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('users.index')
            ->with('success', __('users.deleted', ['name' => $name]));
    }

    /**
     * Roles que ofrece la interfaz: los del sistema respetan el orden de
     * config('roles.system_roles') y el resto sigue alfabético, para que la
     * lista sea estable y no dependa de cómo vengan las filas.
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function roleOptions(): array
    {
        $systemOrder = config('roles.system_roles', []);

        return Role::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->sortBy(fn (Role $role): int => match (true) {
                in_array($role->name, $systemOrder, true) => array_search($role->name, $systemOrder, true),
                default => count($systemOrder),
            })
            ->values()
            ->map(fn (Role $role): array => ['id' => $role->id, 'name' => $role->name])
            ->all();
    }
}
