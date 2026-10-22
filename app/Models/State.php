<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class State extends Model
{
    /** cities.state_id guarda o código IBGE da UF (states.code), não o id de states. */
    public function cities()
    {
        return $this->hasMany(City::class, 'state_id', 'code');
    }
}
