<?php

use App\Filament\Resources\AcademicYears\AcademicYearResource;
use App\Filament\Resources\Companies\CompanyResource;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\DocumentTemplates\DocumentTemplateResource;
use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\Enrollments\EnrollmentResource;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Plans\PlanResource;
use App\Filament\Resources\Suppliers\SupplierResource;
use App\Filament\Resources\Teachers\TeacherResource;
use App\Filament\Resources\Users\UserResource;

/*
|--------------------------------------------------------------------------
| Visibilidade dos itens do menu
|--------------------------------------------------------------------------
|
| Chave: classe do Resource. Sem entrada = visível para qualquer usuário do painel
| (o acesso continua limitado pelas policies / canAccess()).
|
| Cada regra aceita:
|   'permission' => 'nome_da_permissao'   (tem prioridade; usa o Spatie Permission)
|   'roles'      => ['admin', 'coordinator']
|
| O perfil "admin" sempre vê tudo. Para migrar um item de perfis para permissões,
| basta trocar 'roles' por 'permission' (e criar a permissão no RolePermissionSeeder).
| Isto controla só o menu, não substitui a autorização.
*/

$staff = ['roles' => ['coordinator'], 'permission' => null];

return [
    'access' => [
        // Secretaria e financeiro: indisponíveis para professores
        EnrollmentResource::class => $staff,
        InvoiceResource::class => $staff,
        PlanResource::class => $staff,
        CustomerResource::class => $staff,
        TeacherResource::class => $staff,
        SupplierResource::class => $staff,
        DocumentResource::class => $staff,
        DocumentTemplateResource::class => $staff,
        CompanyResource::class => $staff,
        AcademicYearResource::class => $staff,

        // Administração
        UserResource::class => ['roles' => ['coordinator'], 'permission' => null],
    ],
];
