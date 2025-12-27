<?php

namespace App\Enums;

enum InvoiceType: string
{
    case ENROLLMENT = 'enrollment';      // Taxa de matrícula
    case TUITION = 'tuition';            // Mensalidade
    case SERVICE = 'service';            // Serviço adicional
    case MATERIAL = 'material';          // Material didático
    case OTHER = 'other';                // Outras entradas
}
