<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CustomerTier;
use App\Models\SecurityLog;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\WholesaleProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DB::transaction(function (): void {
            $this->seedRolesUsersAndProfiles();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function seedRolesUsersAndProfiles(): void
    {
        $permissions = [
            'catalog.view',
            'catalog.create',
            'catalog.edit',
            'catalog.delete',
            'inventory.view',
            'inventory.adjust',
            'inventory.batches.manage',
            'inventory.locations.manage',
            'orders.view',
            'orders.create',
            'orders.update_status',
            'orders.dispatch_fefo',
            'orders.cancel',
            'payments.view',
            'payments.verify',
            'payments.refund',
            'returns.view',
            'returns.inspect',
            'returns.resolve',
            'wholesale.view',
            'wholesale.approve',
            'wholesale.manage_credit',
            'marketing.coupons',
            'marketing.blog',
            'marketing.newsletter',
            'marketing.abandoned_carts',
            'security.logs.view',
            'users.manage',
            'reports.view',
            'settings.manage',
            'announcements.manage',
            'reviews.moderate',
        ];

        foreach ($permissions as $permissionName) {
            Permission::query()->firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        $roleDefinitions = [
            'SuperAdmin' => $permissions,
            'Administrador' => [
                'catalog.view',
                'catalog.create',
                'catalog.edit',
                'catalog.delete',
                'inventory.view',
                'inventory.batches.manage',
                'orders.view',
                'orders.create',
                'orders.update_status',
                'orders.dispatch_fefo',
                'orders.cancel',
                'payments.view',
                'payments.verify',
                'payments.refund',
                'returns.view',
                'returns.inspect',
                'returns.resolve',
                'wholesale.view',
                'wholesale.approve',
                'wholesale.manage_credit',
                'marketing.coupons',
                'marketing.blog',
                'marketing.newsletter',
                'marketing.abandoned_carts',
                'security.logs.view',
                'users.manage',
                'reports.view',
                'settings.manage',
                'announcements.manage',
                'reviews.moderate',
            ],
            'Almacenero' => [
                'catalog.view',
                'inventory.view',
                'inventory.adjust',
                'inventory.batches.manage',
                'inventory.locations.manage',
                'orders.view',
                'orders.update_status',
                'orders.dispatch_fefo',
                'returns.view',
                'returns.inspect',
            ],
            'Vendedor B2B' => [
                'catalog.view',
                'orders.view',
                'orders.create',
                'payments.view',
                'returns.view',
                'wholesale.view',
            ],
            'Cliente' => [
                'catalog.view',
                'orders.view',
                'orders.create',
                'returns.view',
            ],
        ];

        foreach ($roleDefinitions as $roleName => $rolePermissions) {
            $role = Role::query()->firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            $role->syncPermissions($rolePermissions);
        }

        $superAdmin = $this->upsertUser(
            email: 'superadmin@glamora.pe',
            fillableAttributes: [
                'name' => 'Valeria Mendoza (SuperAdmin)',
                'password' => Hash::make('GlamoraSuper#2026'),
                'phone' => '+51 999 888 777',
                'document_type' => 'DNI',
                'document_number' => '45128901',
                'default_address' => 'Av. Pardo y Aliaga 640, Of. 902',
                'district' => 'San Isidro',
                'city' => 'Lima',
                'last_login_at' => now()->subHours(2),
            ],
            sensitiveAttributes: [
                'email_verified_at' => now(),
                'customer_tier' => CustomerTier::Oro,
                'two_factor_enabled' => false,
                'is_active' => true,
            ],
            role: 'SuperAdmin',
        );

        $admin = $this->upsertUser(
            email: 'admin@glamora.pe',
            fillableAttributes: [
                'name' => 'Camila Paredes (Admin)',
                'password' => Hash::make('GlamoraAdmin#2026'),
                'phone' => '+51 987 111 222',
                'document_type' => 'DNI',
                'document_number' => '46890123',
                'default_address' => 'Av. Larco 1150, Int. 401',
                'district' => 'Miraflores',
                'city' => 'Lima',
                'last_login_at' => now()->subHours(5),
            ],
            sensitiveAttributes: [
                'email_verified_at' => now(),
                'customer_tier' => CustomerTier::Minorista,
                'two_factor_enabled' => false,
                'is_active' => true,
            ],
            role: 'Administrador',
        );

        $almacenero = $this->upsertUser(
            email: 'almacen@glamora.pe',
            fillableAttributes: [
                'name' => 'Diego Quispe (Almacén)',
                'password' => Hash::make('GlamoraAlmacen#2026'),
                'phone' => '+51 945 333 444',
                'document_type' => 'DNI',
                'document_number' => '70123456',
                'default_address' => 'Almacén Central: Lima Metropolitana, Perú',
                'district' => 'Ate',
                'city' => 'Lima',
                'last_login_at' => now()->subDay(),
            ],
            sensitiveAttributes: [
                'email_verified_at' => now(),
                'customer_tier' => CustomerTier::Minorista,
                'two_factor_enabled' => false,
                'is_active' => true,
            ],
            role: 'Almacenero',
        );

        $vendedorB2b = $this->upsertUser(
            email: 'b2b@glamora.pe',
            fillableAttributes: [
                'name' => 'Luciana Salazar (B2B Oro)',
                'password' => Hash::make('GlamoraB2B#2026'),
                'phone' => '+51 982 555 666',
                'document_type' => 'RUC',
                'document_number' => '20604891234',
                'default_address' => 'Av. Primavera 890, Local 2',
                'district' => 'Santiago de Surco',
                'city' => 'Lima',
                'last_login_at' => now()->subHours(3),
            ],
            sensitiveAttributes: [
                'email_verified_at' => now(),
                'customer_tier' => CustomerTier::Oro,
                'two_factor_enabled' => false,
                'is_active' => true,
            ],
            role: 'Vendedor B2B',
        );

        $cliente = $this->upsertUser(
            email: 'cliente@glamora.pe',
            fillableAttributes: [
                'name' => 'Sofía Navarro',
                'password' => Hash::make('GlamoraCliente#2026'),
                'phone' => '+51 912 777 888',
                'document_type' => 'DNI',
                'document_number' => '72456789',
                'default_address' => 'Calle Berlín 345, Dpto 502',
                'district' => 'Miraflores',
                'city' => 'Lima',
                'last_login_at' => now()->subDays(2),
            ],
            sensitiveAttributes: [
                'email_verified_at' => now(),
                'customer_tier' => CustomerTier::Minorista,
                'two_factor_enabled' => false,
                'is_active' => true,
            ],
            role: 'Cliente',
        );

        $pendingApplicant = $this->upsertUser(
            email: 'postulante.b2b@glamora.pe',
            fillableAttributes: [
                'name' => 'Andrea Torres (Estética Arequipa)',
                'password' => Hash::make('GlamoraPostulante#2026'),
                'phone' => '+51 954 321 987',
                'document_type' => 'RUC',
                'document_number' => '20609112233',
                'default_address' => 'Calle Mercaderes 218',
                'district' => 'Cercado',
                'city' => 'Arequipa',
            ],
            sensitiveAttributes: [
                'email_verified_at' => now(),
                'customer_tier' => CustomerTier::Minorista,
                'two_factor_enabled' => false,
                'is_active' => true,
            ],
            role: 'Cliente',
        );

        WholesaleProfile::query()->updateOrCreate(
            ['user_id' => $vendedorB2b->id],
            [
                'company_name' => 'Salón & Spa Aura Dorada S.A.C.',
                'trade_name' => 'Aura Dorada Beauty Studio',
                'ruc' => '20604891234',
                'business_type' => 'Salón de belleza y spa',
                'contact_name' => 'Luciana Salazar',
                'contact_phone' => '+51 982 555 666',
                'contact_email' => 'b2b@glamora.pe',
                'fiscal_address' => 'Av. Primavera 890, Local 2',
                'district' => 'Santiago de Surco',
                'city' => 'Lima',
                'requested_tier' => CustomerTier::Oro,
                'approved_tier' => CustomerTier::Oro,
                'credit_limit' => 25000.00,
                'credit_used' => 1126.90,
                'payment_terms_days' => 30,
                'status' => 'approved',
                'approved_by' => $admin->id,
                'approved_at' => now()->subDays(45),
                'notes' => 'Cliente estratégico Lima Sur con historial puntual de pagos a 30 días.',
            ]
        );

        WholesaleProfile::query()->updateOrCreate(
            ['user_id' => $pendingApplicant->id],
            [
                'company_name' => 'Centro Dermoestético Misti E.I.R.L.',
                'trade_name' => 'Misti Skin Studio',
                'ruc' => '20609112233',
                'business_type' => 'Centro estético',
                'contact_name' => 'Andrea Torres',
                'contact_phone' => '+51 954 321 987',
                'contact_email' => 'postulante.b2b@glamora.pe',
                'fiscal_address' => 'Calle Mercaderes 218',
                'district' => 'Cercado',
                'city' => 'Arequipa',
                'requested_tier' => CustomerTier::Plata,
                'approved_tier' => null,
                'credit_limit' => 0.00,
                'credit_used' => 0.00,
                'payment_terms_days' => 15,
                'status' => 'pending',
                'notes' => 'Solicita línea inicial de S/ 8,000 para reposición mensual de sérums y solares.',
            ]
        );

        UserAddress::query()->updateOrCreate(
            ['user_id' => $cliente->id, 'label' => 'Departamento Miraflores'],
            [
                'recipient_name' => 'Sofía Navarro',
                'phone' => '+51 912 777 888',
                'address_line' => 'Calle Berlín 345, Dpto 502',
                'district' => 'Miraflores',
                'city' => 'Lima',
                'reference' => 'Frente al parque Kennedy, edificio blanco',
                'is_default' => true,
            ]
        );

        UserAddress::query()->updateOrCreate(
            ['user_id' => $vendedorB2b->id, 'label' => 'Sede Principal Surco'],
            [
                'recipient_name' => 'Luciana Salazar',
                'phone' => '+51 982 555 666',
                'address_line' => 'Av. Primavera 890, Local 2',
                'district' => 'Santiago de Surco',
                'city' => 'Lima',
                'reference' => 'Puerta de recepción B2B, horario 9am - 6pm',
                'is_default' => true,
            ]
        );

        if (SecurityLog::query()->count() === 0) {
            SecurityLog::query()->create([
                'user_id' => $superAdmin->id,
                'event_type' => 'system_bootstrap',
                'severity' => 'info',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'GlamoraSeeder/1.0',
                'description' => 'Inicialización de roles, permisos y perfiles operativos Glamora.',
                'metadata' => ['roles_count' => 5, 'permissions_count' => count($permissions)],
            ]);

            SecurityLog::query()->create([
                'user_id' => $admin->id,
                'event_type' => 'wholesale_approved',
                'severity' => 'info',
                'ip_address' => '190.232.14.18',
                'user_agent' => 'Mozilla/5.0',
                'description' => 'Aprobación de perfil mayorista Oro y línea de crédito S/ 25,000.00 para Aura Dorada S.A.C.',
                'metadata' => ['ruc' => '20604891234', 'credit_limit' => 25000.00],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $fillableAttributes
     * @param  array<string, mixed>  $sensitiveAttributes
     */
    private function upsertUser(
        string $email,
        array $fillableAttributes,
        array $sensitiveAttributes,
        string $role,
    ): User {
        $user = User::query()->firstOrNew(['email' => $email]);
        $user->fill($fillableAttributes);
        $user->forceFill($sensitiveAttributes);
        $user->save();
        $user->syncRoles([$role]);

        return $user;
    }
}
