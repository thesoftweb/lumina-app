<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    /** cities.state_id guarda o código IBGE da UF (states.code), não o id de states. */
    public function state()
    {
        return $this->belongsTo(State::class, 'state_id', 'code');
    }
}
