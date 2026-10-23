<?php

use App\Filament\Resources\AcademicYears\AcademicYearResource;
use App\Filament\Resources\Companies\CompanyResource;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Enrollments\EnrollmentResource;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\IssuedDocuments\IssuedDocumentResource;
use App\Filament\Resources\Levels\LevelResource;
use App\Filament\Resources\Plans\PlanResource;
use App\Filament\Resources\Subjects\SubjectResource;
use App\Filament\Resources\Suppliers\SupplierResource;
use App\Filament\Resources\Teachers\TeacherResource;
use App\Filament\Resources\Terms\TermResource;
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
|   'roles'      => ['admin', 'secretary']
|
| O perfil "admin" sempre vê tudo. Para migrar um item de perfis para permissões,
| basta trocar 'roles' por 'permission' (e criar a permissão no RolePermissionSeeder).
| Isto controla só o menu, não substitui a autorização.
*/

$office = ['roles' => ['secretary'], 'permission' => null];
$adminOnly = ['roles' => ['admin'], 'permission' => null];

return [
    'access' => [
        // Secretaria: matrículas, cobranças, cadastros, documentos e estrutura do ano (professor não vê)
        EnrollmentResource::class => $office,
        InvoiceResource::class => $office,
        CustomerResource::class => $office,
        TeacherResource::class => $office,
        IssuedDocumentResource::class => $office,
        AcademicYearResource::class => $office,
        TermResource::class => $office,
        LevelResource::class => $office,
        SubjectResource::class => $office,

        // Direção: preços, contas a pagar, dados da empresa e usuários
        PlanResource::class => $adminOnly,
        SupplierResource::class => $adminOnly,
        CompanyResource::class => $adminOnly,
        UserResource::class => $adminOnly,
    ],
];
