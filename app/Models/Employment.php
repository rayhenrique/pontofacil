<?php

namespace App\Models;

/**
 * Representação do vínculo empregatício / funcional do trabalhador.
 * No PontoFácil (Single-Tenant), mapeia de forma direta e interoperável
 * para o modelo Employee.
 */
class Employment extends Employee
{
    protected $table = 'employees';
}
