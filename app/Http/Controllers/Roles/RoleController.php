<?php

namespace App\Http\Controllers\Roles;

use App\Actions\Roles\DeleteRole;
use App\Actions\Roles\SyncRolePermissions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Roles\DeleteRoleRequest;
use App\Http\Requests\Roles\StoreRoleRequest;
use App\Http\Requests\Roles\UpdateRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function __construct(
        private readonly DeleteRole $deleteRole,
        private readonly SyncRolePermissions $syncRolePermissions,
    ) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $roles = Role::query()
            ->with('permissions:id,name')
            ->withCount('users')
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(config('crm.per_page'))
            ->withQueryString()
            ->through(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'is_system' => $role->isSystemRole(),
                'is_full_access' => $role->name === config('roles.full_access_role'),
                'users_count' => $role->users_count,
                'permissions' => $role->permissions->pluck('name')->sort()->values()->all(),
            ]);

        return Inertia::render('Roles/Index', [
            'roles' => $roles,
            'filters' => ['search' => $search],
            // El total del catálogo viaja con la página para que la interfaz
            // no tenga que contarlo por su cuenta.
            'permissionsTotal' => Permission::query()->count(),
            'can' => [
                'create' => $request->user()->can('create', Role::class),
                'update' => $request->user()->can('update', Role::class),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Roles/Create', [
            'permissions' => $this->permissionGroups(),
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $role = Role::create(['name' => $request->validated('name'), 'guard_name' => 'web']);

        $this->syncRolePermissions->handle($role, $request->validated('permissions', []));

        return redirect()
            ->route('roles.show', $role)
            ->with('success', __('roles.created', ['name' => $role->name]));
    }

    public function show(Request $request, Role $role): Response
    {
        $role->load('permissions:id,name');

        return Inertia::render('Roles/Show', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'is_system' => $role->isSystemRole(),
                'is_full_access' => $role->name === config('roles.full_access_role'),
                'users_count' => $role->users()->count(),
                'permissions' => $role->permissions->pluck('name')->sort()->values()->all(),
                'created_at' => $role->created_at?->toIso8601String(),
                'updated_at' => $role->updated_at?->toIso8601String(),
            ],
            'permissions' => $this->permissionGroups(),
            'permissionsTotal' => Permission::query()->count(),
            'can' => [
                'update' => $request->user()->can('update', $role),
                'delete' => $request->user()->can('delete', $role),
            ],
        ]);
    }

    public function edit(Role $role): Response
    {
        $role->load('permissions:id,name');

        return Inertia::render('Roles/Edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'is_system' => $role->isSystemRole(),
                'is_full_access' => $role->name === config('roles.full_access_role'),
                'permissions' => $role->permissions->pluck('id')->all(),
            ],
            'permissions' => $this->permissionGroups(),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $role->update(['name' => $request->validated('name')]);

        $this->syncRolePermissions->handle($role, $request->validated('permissions', []));

        return redirect()
            ->route('roles.show', $role)
            ->with('success', __('roles.updated', ['name' => $role->name]));
    }

    public function destroy(DeleteRoleRequest $request, Role $role): RedirectResponse
    {
        $name = $role->name;

        try {
            $this->deleteRole->handle($role);
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('roles.index')
            ->with('success', __('roles.deleted', ['name' => $name]));
    }

    /**
     * Catálogo agrupado para pintar, armado desde config('roles.permissions')
     * para que la interfaz no repita etiquetas y un permiso nuevo aparezca sin
     * tocar Vue.
     *
     * @return array<int, array{group: string, permissions: array<int, array{id: int, name: string, label: string}>}>
     */
    private function permissionGroups(): array
    {
        $permissions = Permission::query()->orderBy('id')->get(['id', 'name']);

        return $permissions
            ->groupBy(fn (Permission $permission): string => $permission->group())
            ->map(fn ($group, $name): array => [
                'group' => $name,
                'permissions' => $group
                    ->map(fn (Permission $permission): array => [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'label' => $permission->label(),
                    ])->values()->all(),
            ])
            ->values()
            ->all();
    }
}
